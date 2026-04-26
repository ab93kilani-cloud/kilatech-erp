const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_FINAL_REPAIRED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Update Dossier node to include product ID
let dossierNode = nodes.find(n => n.name === 'Dossier de Redaction Final');
if (dossierNode) {
    dossierNode.parameters.jsCode = `const getSafe = (nodeName) => {
    try {
        const node = $(nodeName);
        if (node && node.first()) {
            return node.first().json.text || 'N/A';
        }
    } catch (e) {}
    return 'N/A';
};

// Extract image URL from Architect
let imageUrl = 'N/A';
try {
    const archResult = JSON.parse($('Architect').first().json.text);
    imageUrl = archResult.image_url || 'N/A';
} catch (e) {}

return {
    product_id: $('PRODUIT').first().json.id,
    product_name: $('PRODUIT').first().json.name,
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    analyste: getSafe("📊 10. L'Analyste"),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef'),
    image_url: imageUrl
};`;
    dossierNode.parameters.mode = 'runOnceForAllItems';
}

// 2. Add "Le Directeur" node (HTTP Request to Laravel)
const directorNode = {
    "parameters": {
        "method": "POST",
        "url": "=https://app.kilatech.ma/api/seo-v2/update/{{ $node[\"Dossier de Redaction Final\"].json.product_id }}",
        "sendQuery": true,
        "queryParameters": {
            "parameters": [
                {
                    "name": "api_token",
                    "value": "KILATECH_7382"
                }
            ]
        },
        "sendBody": true,
        "bodyParameters": {
            "parameters": [
                {
                    "name": "seo_content",
                    "value": "={{ Buffer.from($node[\"Le Finaliseur\"].json.text).toString('base64') }}"
                },
                {
                    "name": "seo_score",
                    "value": "={{ parseInt($node[\"Dossier de Redaction Final\"].json.analyste.match(/SCORE SEO : (\\d+)/)?.[1] || 85) }}"
                },
                {
                    "name": "social_linkedin_post",
                    "value": "={{ $node[\"Dossier de Redaction Final\"].json.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A' }}"
                },
                {
                    "name": "social_facebook_post",
                    "value": "={{ $node[\"Dossier de Redaction Final\"].json.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A' }}"
                }
            ]
        },
        "options": {}
    },
    "id": "director-node-id",
    "name": "🏢 11. Le Directeur",
    "type": "n8n-nodes-base.httpRequest",
    "typeVersion": 4,
    "position": [2600, 240]
};

// Add node if not exists
if (!nodes.find(n => n.name === '🏢 11. Le Directeur')) {
    nodes.push(directorNode);
}

// 3. Update connections
// Connect Finaliseur to Director
if (!wf[0].connections['Le Finaliseur']) wf[0].connections['Le Finaliseur'] = { main: [[]] };
wf[0].connections['Le Finaliseur'].main[0] = [{ node: "🏢 11. Le Directeur", type: "main", index: 0 }];

// Connect Director to Manager
if (!wf[0].connections['🏢 11. Le Directeur']) wf[0].connections['🏢 11. Le Directeur'] = { main: [[]] };
wf[0].connections['🏢 11. Le Directeur'].main[0] = [{ node: "📱 12. Le Manager", type: "main", index: 0 }];

fs.writeFileSync('WORKFLOW_DIRECTEUR_CONNECTED.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_DIRECTEUR_CONNECTED.json created');
