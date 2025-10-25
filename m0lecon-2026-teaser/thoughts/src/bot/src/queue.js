const redis = require('redis');

const sleep_time = 20;

class VisitQueue {
    constructor(maxConcurrency = 1) {
        this.client = null;
        this.queueKey = 'visit_queue';
        this.concurrentJobsKey = 'visit_concurrent_jobs';
        this.maxConcurrency = maxConcurrency;
        this.isProcessing = false;
    }

    async connect() {
        if (!this.client) {
            this.client = redis.createClient({ url: 'redis://redis:6379' });
            await this.client.connect();
        }
    }

    async disconnect() {
        if (this.client) {
            await this.client.disconnect();
            this.client = null;
        }
    }

    async enqueue(url, userSecret, logInfo, logError) {
        await this.connect();

        const runningJobs = await this.getRunningJobsCount();
        if (runningJobs >= this.maxConcurrency) {
            logInfo(`Other jobs running, waiting...`);
        }

        const jobData = JSON.stringify({ url, userSecret, timestamp: Date.now() });
        await this.client.lPush(this.queueKey, jobData);
        logInfo(`Job queued: ${url}`);

        if (!this.isProcessing) {
            this.processQueue();
        }
    }

    async processQueue() {
        if (this.isProcessing) {
            return;
        }

        this.isProcessing = true;
        await this.connect();

        try {
            while (true) {
                const runningJobs = await this.getRunningJobsCount();
                if (runningJobs >= this.maxConcurrency) {
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    continue;
                }

                const jobData = await this.client.brPop(this.queueKey, 1);
                if (!jobData) {
                    break;
                }

                const job = JSON.parse(jobData.element);
                const jobId = `job_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

                await this.client.setEx(`${this.concurrentJobsKey}:${jobId}`, sleep_time, JSON.stringify(job));

                try {
                    const { visit } = require('./bot');
                    await visit(job.url, job.userSecret);
                } catch (error) {
                } finally {
                    await this.client.del(`${this.concurrentJobsKey}:${jobId}`);
                    process.exit(0);
                }
            }
        } catch (error) {
        } finally {
            this.isProcessing = false;
        }
    }

    async getQueueLength() {
        await this.connect();
        return await this.client.lLen(this.queueKey);
    }

    async getRunningJobsCount() {
        await this.connect();
        const keys = await this.client.keys(`${this.concurrentJobsKey}:*`);
        return keys.length;
    }

    async isProcessingJob() {
        return (await this.getRunningJobsCount()) > 0;
    }
}

module.exports = { VisitQueue, sleep_time };