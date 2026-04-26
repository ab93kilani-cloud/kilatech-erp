const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_WITHOUT_ANALYST.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Update Dossier node (Remove Analyst, Add simple scoring)
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
    const archJson = $('Architect').first().json.text;
    const archResult = typeof archJson === 'string' ? JSON.parse(archJson) : archJson;
    imageUrl = archResult.image_url || 'N/A';
} catch (e) {}

return {
    product_id: $('PRODUIT').first().json.id,
    product_name: $('PRODUIT').first().json.name,
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef'),
    image_url: imageUrl
};`;
    dossierNode.parameters.mode = 'runOnceForAllItems';
}

// 2. Update Director node (Fix data mapping)
let directorNode = nodes.find(n => n.name === '🏢 11. Le Directeur');
if (directorNode) {
    directorNode.parameters.bodyParameters.parameters = [
        {
            "name": "seo_content",
            "value": "={{ Buffer.from($node[\"Le Finaliseur\"].json.text).toString('base64') }}"
        },
        {
            "name": "seo_score",
            "value": "={{ Math.min(95, 40 + Math.floor($node[\"Le Finaliseur\"].json.text.length / 50)) }}"
        },
        {
            "name": "social_linkedin_post",
            "value": "={{ $node[\"Dossier de Redaction Final\"].json.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A' }}"
        },
        {
            "name": "social_facebook_post",
            "value": "={{ $node[\"Dossier de Redaction Final\"].json.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A' }}"
        }
    ];
}

fs.writeFileSync('WORKFLOW_SANS_ANALYSTE_FINAL.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_SANS_ANALYSTE_FINAL.json created');
