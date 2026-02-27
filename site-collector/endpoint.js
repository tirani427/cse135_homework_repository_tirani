
const express = require('express');
const fs = require('fs');
const path = require('path');

const app = express();
const PORT = 3005;
const LOG_FILE = path.join(__dirname, 'analytics.jsonl');

//CORS headers - required when collector and endpoint are on different origins
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', 'https://cse135tirani.site');
    res.header('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type');
    if(req.method === 'OPTIONS') {
        return res.sendStatus(204);
    }
    next();
});

app.use(express.json());

app.post('/collect', (req, res) => {
    const payload = req.body;

    if(!payload || !payload.url || !payload.type){
        return res.status(400).json({error: 'Missing required fields: url, type'});
    }

    payload.serverTimestamp = new Date().toISOString();

    payload.ip = req.ip;

    const line = JSON.stringify(payload)+'\n';
    fs.appendFile(LOG_FILE, line, (err) => {
        if(err){
            console.error('Write error:', err);
            return res,sendStatus(500);
        }
        res.sendStatus(204);
    });
});

app.use(express.static(__dirname));

app.listen(PORT, () =>{
    console.log(`Analytics endpoint listening on http://localhost:${PORT}`);
    console.log(`Test page: http://localhost:${PORT}/test.html`);
    console.log(`Data file: ${LOG_FILE}`);
});