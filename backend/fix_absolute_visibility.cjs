const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_PERFECTION_FINALE.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Double-Reinforce the Image URL in Finalizer
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    // We use a more explicit prompt with a fallback
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication Kilatech.
MISSION : Produire 3 éléments SEO séparés.

DOSSIER :
- URL_PHOTO : {{ $("PRODUIT").first().json.images[0]?.url || "https://kilatech.ma/storage/default-product.png" }}
- DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}
- TEXTE : {{ $node["Dossier de Redaction Final"].json.redacteur }}

CONSIGNES :
Réponds UNIQUEMENT en JSON :
{
  "seo_title": "...",
  "seo_description": "...",
  "seo_content": "<img src=\\"{{ $("PRODUIT").first().json.images[0]?.url || "https://kilatech.ma/storage/default-product.png" }}\\" style=\\"width:100%; border-radius:20px; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); margin-bottom: 2rem;\\"> <h1>...</h1> ..."
}

INTERDICTION : L'attribut 'src' ne doit JAMAIS être vide ou égal à "image_url". Utilise l'URL_PHOTO fournie.`;
}

// 2. Ensure Prepa Transfert is rock solid
let prepaNode = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepaNode) {
    prepaNode.parameters.jsCode = `let finalData = {};
try {
  let rawText = items[0].json.output[0].content[0].text;
  if (rawText.includes('\\\`\\\`\\\`json')) {
      rawText = rawText.split('\\\`\\\`\\\`json').pop().split('\\\`\\\`\\\`').shift().trim();
  } else if (rawText.includes('\\\`\\\`\\\`')) {
      rawText = rawText.split('\\\`\\\`\\\`').pop().split('\\\`\\\`\\\`').shift().trim();
  }
  finalData = JSON.parse(rawText);
} catch (e) {
  finalData = { 
    seo_content: items[0].json.output[0].content[0].text,
    seo_title: $('PRODUIT').first().json.name,
    seo_description: "Expertise Kilatech au Maroc."
  };
}

return {
  product_id: $('Dossier de Redaction Final').first().json.product_id,
  seo_title: finalData.seo_title || 'Titre',
  seo_description: finalData.seo_description || 'Description',
  seo_content: Buffer.from(finalData.seo_content || "").toString('base64'),
  seo_score: Math.min(98, 45 + Math.floor((finalData.seo_content || "").length / 40)),
  social_linkedin_post: $('Dossier de Redaction Final').first().json.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: $('Dossier de Redaction Final').first().json.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

fs.writeFileSync('WORKFLOW_ABSOLU.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_ABSOLU.json created');
