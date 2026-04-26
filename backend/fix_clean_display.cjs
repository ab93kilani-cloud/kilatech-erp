const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_MASTER_KILATECH.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Update Finalizer to return JSON with 3 distinct parts
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication. 
MISSION : Produire les 3 éléments SEO séparés.

DOSSIER :
- IMAGE : {{ $("PRODUIT").first().json.images[0]?.url }}
- DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}
- TEXTE : {{ $node["Dossier de Redaction Final"].json.redacteur }}

CONSIGNES :
Réponds UNIQUEMENT en format JSON avec ces 3 clés :
1. "seo_title" : Un titre accrocheur (max 60 chars).
2. "seo_description" : Un résumé pro (max 160 chars).
3. "seo_content" : L'article HTML complet. 
   - COMMENCE par l'image avec le style et l'ALT du Designer.
   - CONTINUE avec les titres H1, H2 et le texte riche.
   - INTERDICTION d'utiliser <html>, <head> ou <body>. Utilise seulement <h1>, <h2>, <p>, <ul>, <img>.`;
}

// 2. Update Prepa Transfert to parse the JSON and send separate fields
let prepaNode = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepaNode) {
    prepaNode.parameters.jsCode = `// Parse the JSON from Finalizer
let finalData = {};
try {
  const rawText = items[0].json.output[0].content[0].text;
  finalData = JSON.parse(rawText.replace(/\`\\`json/g, '').replace(/\`\\`/g, '').trim());
} catch (e) {
  // Fallback if AI fails JSON
  finalData = { 
    seo_content: items[0].json.output[0].content[0].text,
    seo_title: $('PRODUIT').first().json.name,
    seo_description: "Découvrez notre gamme de matériel médical sur Kilatech.ma"
  };
}

const productId = $('Dossier de Redaction Final').first().json.product_id;
const cmText = $('Dossier de Redaction Final').first().json.cm;

return {
  product_id: productId,
  seo_title: finalData.seo_title,
  seo_description: finalData.seo_description,
  seo_content: Buffer.from(finalData.seo_content).toString('base64'), // Still base64 for safety
  seo_score: Math.min(95, 40 + Math.floor(finalData.seo_content.length / 50)),
  social_linkedin_post: cmText.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: cmText.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

// 3. Update Director to send ALL fields
let directorNode = nodes.find(n => n.name === '🏢 11. Le Directeur');
if (directorNode) {
    directorNode.parameters.bodyParameters.parameters = [
        { "name": "seo_title", "value": "={{ $json.seo_title }}" },
        { "name": "seo_description", "value": "={{ $json.seo_description }}" },
        { "name": "seo_content", "value": "={{ $json.seo_content }}" },
        { "name": "seo_score", "value": "={{ $json.seo_score }}" },
        { "name": "social_linkedin_post", "value": "={{ $json.social_linkedin_post }}" },
        { "name": "social_facebook_post", "value": "={{ $json.social_facebook_post }}" }
    ];
}

fs.writeFileSync('WORKFLOW_CLEAN_DISPLAY.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_CLEAN_DISPLAY.json created');
