import express from "express";
import multer from "multer";
import visit from "./bot.js";
import { uploadFile, uploadText } from "./utils.js"

const BACKEND = "http://backend";

const app = express();
const upload = multer();

app.set("view engine", "ejs");
app.set("views", `${process.cwd()}/views`);

app.use(express.urlencoded({ extended: false }));

app.use((req, res, next) => {
    res.header("content-security-policy", "default-src 'none'");
    next();
});

app.get('/', async (req, res) => {
    try {
        const response = await fetch(`${BACKEND}/files.php`);
        const data = await response.json();
        const files = Array.isArray(data.files) ? data.files : [];
        return res.render('files', { files });
    } catch (e) {
        console.log(e);
        res.status(500).send("Error");
    }
});

app.post('/', upload.any(), async (req, res) => {
    let fileBuffer = null;
    let fileName = null;
    const fileId = Math.floor(Math.random() * 1e12);

    if (Array.isArray(req.files) && req.files.length > 0) {
        const picked = req.files.find(f => f.fieldname === 'file') || req.files[0];
        if (picked && picked.buffer) {
            fileBuffer = picked.buffer;
            if (picked.originalname) fileName = picked.originalname;
        }
    }
    if (!fileBuffer && typeof req.body?.file === 'string')
        fileBuffer = Buffer.from(req.body.file, 'utf8');
    if (!fileBuffer)
        return res.status(400).send("No file uploaded");

    try {
        let response = fileName ? await uploadFile(fileName, fileBuffer, fileId, BACKEND) : await uploadText(fileBuffer, fileId, BACKEND);

        const res_json = await response.json();
        if (!res_json.success) throw new Error("file upload went wrong");
        
        return res.redirect(303, `/file/${fileId}`);
    } catch (e) {
        console.log(e);
        res.status(500).send("Error");
    }
});

app.get('/file/:id', async (req, res) => {
    try {
        let id = req.params.id;
        if (!id || isNaN(Number(id)))
            return res.status(400).send("Invalid file ID");
        let response = await fetch(`${BACKEND}/file.php?id=${parseInt(id)}`)
        let data = await response.text();
        res.header('content-type', response.headers.get('content-type') || 'text/plain');
        return res.send(data);
    } catch (e) {
        console.log(e);
        res.status(500).send("Error");
    }
});

app.get('/files', async (req, res) => {
    try {
        let response = await fetch(`${BACKEND}/files.php`);
        let data = await response.json();
        res.set("content-type", "application/json");
        res.send(data);
    } catch (e) {
        console.log(e);
        res.status(500).send("Error");
    }
});

app.get("/visit", (req, res) => {
    const id = req.query.id;
    res.set("content-type", "text/html;charset=utf8");
    if (id && !isNaN(Number(id))) {
        visit(`http://frontend:1337/file/${parseInt(id)}`);
        res.write("<p>Adming will soon visit your page<p>");
    }
    res.end(`<form><label>give me a file id</label><input name='id' placeholder='1'><button>submit</button></form>`);
});

app.listen(1337).on("error", (e) => {
    console.log(e);
    process.exit(1);
}).on("listening", () => {
    console.log("Server started on port 1337");
});
