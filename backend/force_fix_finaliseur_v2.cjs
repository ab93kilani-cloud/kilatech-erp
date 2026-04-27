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
    let jsCode = "let rawText = items[0].json.output[0].content[0].text;\n";
    jsCode += "const dossierNode = $('Dossier de Redaction Final').first().json;\n";
    jsCode += "const imageUrl = dossierNode.image_url;\n";
    jsCode += "\n// 1. Extract body content if AI generated full HTML document\n";
    jsCode += "if (rawText.toLowerCase().includes('<body')) {\n";
    jsCode += "    const bodyMatch = rawText.match(/<body[^>]*>([\\s\\S]*?)<\\/body>/i);\n";
    jsCode += "    if (bodyMatch) {\n";
    jsCode += "        rawText = bodyMatch[1];\n";
    jsCode += "    }\n";
    jsCode += "}\n\n";
    jsCode += "// 2. Remove DOCTYPE, html, head just in case it's malformed\n";
    jsCode += "rawText = rawText.replace(/<\\!DOCTYPE html>/gi, '')\n";
    jsCode += "                 .replace(/<html[^>]*>/gi, '')\n";
    jsCode += "                 .replace(/<head>[\\s\\S]*?<\\/head>/gi, '')\n";
    jsCode += "                 .replace(/<\\/html>/gi, '');\n\n";
    jsCode += "// 3. Remove markdown wrappers\n";
    jsCode += "let cleanText = rawText.replace(/```html\\n?/g, '').replace(/```\\n?/g, '').trim();\n\n";
    jsCode += "// 4. Fix literal \"URL_IMAGE\" if AI failed to replace it\n";
    jsCode += "cleanText = cleanText.replace(/src=[\"']URL_IMAGE[\"']/gi, 'src=\"' + imageUrl + '\"');\n\n";
    jsCode += "// 5. Strip any social posts\n";
    jsCode += "const socialIndex = cleanText.indexOf('<h3>Promotion Sociale</h3>');\n";
    jsCode += "if (socialIndex !== -1) {\n";
    jsCode += "    cleanText = cleanText.substring(0, socialIndex).trim();\n";
    jsCode += "}\n";
    jsCode += "if(cleanText.endsWith('<hr>')) {\n";
    jsCode += "    cleanText = cleanText.substring(0, cleanText.length - 4).trim();\n";
    jsCode += "}\n\n";
    jsCode += "return {\n";
    jsCode += "  product_id: dossierNode.product_id,\n";
    jsCode += "  seo_content: cleanText.trim(),\n";
    jsCode += "  seo_description: dossierNode.meta_desc,\n";
    jsCode += "  seo_score: Math.min(95, 40 + Math.floor(cleanText.length / 50)),\n";
    jsCode += "  social_linkedin_post: dossierNode.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',\n";
    jsCode += "  social_facebook_post: dossierNode.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'\n";
    jsCode += "};\n";

    prepa.parameters.jsCode = jsCode;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Workflow forcefully updated to prevent any HTML formatting issues.');
