const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_CLEAN_DISPLAY_FIXED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Force Finalizer to use the REAL URL instead of a placeholder
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication. 
MISSION : Produire les 3 éléments SEO séparés.

DOSSIER :
- URL_PHOTO_REELLE : {{ $("PRODUIT").first().json.images[0]?.url }}
- DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}
- TEXTE : {{ $node["Dossier de Redaction Final"].json.redacteur }}

CONSIGNES :
Réponds UNIQUEMENT en format JSON :
{
  "seo_title": "...",
  "seo_description": "...",
  "seo_content": "<img src=\\"{{ $("PRODUIT").first().json.images[0]?.url }}\\" alt=\\"...\\" style=\\"...\\"> <h1>...</h1> <p>...</p>"
}

IMPORTANT : Dans "seo_content", remplace bien l'attribut src par l'URL_PHOTO_REELLE fournie ci-dessus.`;
}

// 2. Ensure Prepa Transfert sends content properly
let prepaNode = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepaNode) {
    prepaNode.parameters.jsCode = `let finalData = {};
try {
  let rawText = items[0].json.output[0].content[0].text;
  rawText = rawText.split('\\\`\\\`\\\`json').pop().split('\\\`\\\`\\\`').shift().trim();
  finalData = JSON.parse(rawText);
} catch (e) {
  finalData = { 
    seo_content: items[0].json.output[0].content[0].text,
    seo_title: $('PRODUIT').first().json.name,
    seo_description: "Expert en matériel médical au Maroc."
  };
}

const productId = $('Dossier de Redaction Final').first().json.product_id;
const cmText = $('Dossier de Redaction Final').first().json.cm;

return {
  product_id: productId,
  seo_title: finalData.seo_title,
  seo_description: finalData.seo_description,
  seo_content: Buffer.from(finalData.seo_content).toString('base64'),
  seo_score: Math.min(95, 45 + Math.floor(finalData.seo_content.length / 45)),
  social_linkedin_post: cmText.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: cmText.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

fs.writeFileSync('WORKFLOW_PERFECTION_FINALE.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_PERFECTION_FINALE.json created');
