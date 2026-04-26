const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_FIX_N8N_VERSION.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Update Director node with the EXACT nested path from the screenshot
let directorNode = nodes.find(n => n.name === '🏢 11. Le Directeur');
if (directorNode) {
    directorNode.parameters.bodyParameters.parameters = [
        {
            "name": "seo_content",
            "value": "={{ Buffer.from($node[\"Le Finaliseur\"].json.output[0].content[0].text).toString('base64') }}"
        },
        {
            "name": "seo_score",
            "value": "={{ Math.min(95, 40 + Math.floor($node[\"Le Finaliseur\"].json.output[0].content[0].text.length / 50)) }}"
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

fs.writeFileSync('WORKFLOW_REPARATION_CONFIRMEE.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_REPARATION_CONFIRMEE.json created');
