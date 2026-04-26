const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_DESIGNER_CHAT_MODE.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Re-add "Prepa Transfert" (The critical bridge)
const prepaNode = {
    "parameters": {
        "jsCode": "const text = items[0].json.output[0].content[0].text;\nconst productId = $('Dossier de Redaction Final').first().json.product_id;\nconst cmText = $('Dossier de Redaction Final').first().json.cm;\n\nreturn {\n  product_id: productId,\n  seo_content: Buffer.from(text).toString('base64'),\n  seo_score: Math.min(95, 40 + Math.floor(text.length / 50)),\n  social_linkedin_post: cmText.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',\n  social_facebook_post: cmText.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'\n};"
    },
    "id": "master-prepa-id",
    "name": "📦 Prepa Transfert",
    "type": "n8n-nodes-base.code",
    "typeVersion": 2,
    "position": [2500, 240]
};

if (!nodes.find(n => n.name === '📦 Prepa Transfert')) {
    nodes.push(prepaNode);
}

// 2. Ensure Director uses Prepa Transfert data
let directorNode = nodes.find(n => n.name === '🏢 11. Le Directeur');
if (directorNode) {
    directorNode.position = [2750, 240];
    directorNode.parameters.url = "=https://app.kilatech.ma/api/seo-v2/update/{{ $json.product_id }}";
    directorNode.parameters.bodyParameters.parameters = [
        { "name": "seo_content", "value": "={{ $json.seo_content }}" },
        { "name": "seo_score", "value": "={{ $json.seo_score }}" },
        { "name": "social_linkedin_post", "value": "={{ $json.social_linkedin_post }}" },
        { "name": "social_facebook_post", "value": "={{ $json.social_facebook_post }}" }
    ];
}

// 3. Re-wire connections
// Finaliseur -> Prepa Transfert
wf[0].connections['Le Finaliseur'].main[0] = [{ node: "📦 Prepa Transfert", type: "main", index: 0 }];

// Prepa Transfert -> Director
wf[0].connections['📦 Prepa Transfert'] = { main: [[{ node: "🏢 11. Le Directeur", type: "main", index: 0 }]] };

fs.writeFileSync('WORKFLOW_MASTER_KILATECH.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_MASTER_KILATECH.json created');
