const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Finaliseur Prompt
let finaliseur = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseur) {
    finaliseur.parameters.responses.values[0].content = `=Tu es l'Intégrateur Web de Kilatech.ma.

TEXTE : {{ $node["Dossier de Redaction Final"].json.tisseur }}

INSTRUCTIONS ULTRA STRICTES :
1. Tu DOIS insérer cette image juste après le premier paragraphe :
<img src="{{ $node["Dossier de Redaction Final"].json.image_url }}" alt="{{ $node["PRODUIT"].json.name }}">
2. Tu DOIS renvoyer UNIQUEMENT les paragraphes et les titres.
3. INTERDICTION ABSOLUE de générer <!DOCTYPE html>, <html>, <head> ou <body>.
4. INTERDICTION ABSOLUE d'utiliser des blocs markdown (\`\`\`html).
5. N'ajoute pas les posts sociaux à la fin.`;
}

// Fix Prepa Transfert Node (Ultimate HTML Cleanup)
let prepa = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepa) {
    prepa.parameters.jsCode = `let rawText = items[0].json.output[0].content[0].text;
const dossierNode = $('Dossier de Redaction Final').first().json;
const imageUrl = dossierNode.image_url;

// 1. Extract body content if AI generated full HTML document
if (rawText.toLowerCase().includes('<body')) {
    const bodyMatch = rawText.match(/<body[^>]*>([\\s\\S]*?)<\\/body>/i);
    if (bodyMatch) {
        rawText = bodyMatch[1];
    }
}

// 2. Remove DOCTYPE, html, head just in case it's malformed
rawText = rawText.replace(/<\\!DOCTYPE html>/gi, '')
                 .replace(/<html[^>]*>/gi, '')
                 .replace(/<head>[\\s\\S]*?<\\/head>/gi, '')
                 .replace(/<\\/html>/gi, '');

// 3. Remove markdown wrappers
let cleanText = rawText.replace(/\\`\\`\\`html\\n?/g, '').replace(/\\`\\`\\`\\n?/g, '').trim();

// 4. Fix literal "URL_IMAGE" if AI failed to replace it
cleanText = cleanText.replace(/src=["']URL_IMAGE["']/gi, \`src="\${imageUrl}"\`);

// 5. Strip any social posts
const socialIndex = cleanText.indexOf('<h3>Promotion Sociale</h3>');
if (socialIndex !== -1) {
    cleanText = cleanText.substring(0, socialIndex).trim();
}
if(cleanText.endsWith('<hr>')) {
    cleanText = cleanText.substring(0, cleanText.length - 4).trim();
}

return {
  product_id: dossierNode.product_id,
  seo_content: cleanText.trim(),
  seo_description: dossierNode.meta_desc,
  seo_score: Math.min(95, 40 + Math.floor(cleanText.length / 50)),
  social_linkedin_post: dossierNode.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: dossierNode.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Workflow forcefully updated to prevent any HTML formatting issues.');
