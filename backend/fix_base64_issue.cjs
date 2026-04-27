const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Prepa Transfert node to send RAW text instead of Base64
let prepa = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepa) {
    prepa.parameters.jsCode = `const text = items[0].json.output[0].content[0].text;
const productId = $('Dossier de Redaction Final').first().json.product_id;
const cmText = $('Dossier de Redaction Final').first().json.cm;

return {
  product_id: productId,
  seo_content: text, // Send raw text instead of base64
  seo_score: Math.min(95, 40 + Math.floor(text.length / 50)),
  social_linkedin_post: cmText.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: cmText.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Prepa Transfert node updated to send raw text.');
