const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_ABSOLU.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Simplify the Transfer - NO MORE BASE64, use raw text but ensure it's safe
let prepaNode = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepaNode) {
    prepaNode.parameters.jsCode = `let finalData = {};
try {
  let rawText = items[0].json.output[0].content[0].text;
  // Nettoyage Markdown
  rawText = rawText.replace(/\\\`\\\`\\\`json/g, '').replace(/\\\`\\\`\\\`html/g, '').replace(/\\\`\\\`\\\`/g, '').trim();
  finalData = JSON.parse(rawText);
} catch (e) {
  // Fallback si le JSON est mal formé
  finalData = { 
    seo_content: items[0].json.output[0].content[0].text,
    seo_title: $('PRODUIT').first().json.name,
    seo_description: "Expertise médicale Kilatech."
  };
}

return {
  product_id: $('Dossier de Redaction Final').first().json.product_id,
  seo_title: finalData.seo_title,
  seo_description: finalData.seo_description,
  seo_content: finalData.seo_content, // ENVOI EN TEXTE CLAIR
  seo_score: 85, // Score fixe pour test
  social_linkedin_post: $('Dossier de Redaction Final').first().json.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: $('Dossier de Redaction Final').first().json.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

fs.writeFileSync('WORKFLOW_DIRECT.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_DIRECT.json created');
