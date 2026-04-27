const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Dossier de Redaction Final (Image fallback)
let dossier = nodes.find(n => n.name === 'Dossier de Redaction Final');
if (dossier) {
    dossier.parameters.jsCode = dossier.parameters.jsCode.replace(
        "imageUrl = 'https://app.kilatech.ma/api/storage_proxy.php?path=images/placeholder.jpg';",
        "imageUrl = 'https://app.kilatech.ma/logo.png';"
    );
}

// Fix Finaliseur Prompt
let finaliseur = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseur) {
    finaliseur.parameters.responses.values[0].content = `=Tu es l'Intégrateur Web de Kilatech.ma.
MISSION : Tu dois générer le contenu HTML FINAL de l'article (uniquement les fragments, pas la page entière).

DONNÉES À TA DISPOSITION :
1. TEXTE FINAL : {{ $node["Dossier de Redaction Final"].json.tisseur }}
2. URL IMAGE : {{ $node["Dossier de Redaction Final"].json.image_url }}
3. CONSEILS DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}

RÈGLES ABSOLUES DE FORMATAGE :
1. NE GÉNÈRE SURTOUT PAS de balises <html>, <head>, <title>, <meta> ou <body>. Renvoie UNIQUEMENT les <p>, <h2>, <h3>, <ul> et <img>.
2. AUCUN BLOC MARKDOWN. Ne commence pas par \`\`\`html et ne termine pas par \`\`\`. Renvoie UNIQUEMENT le code HTML pur.
3. L'IMAGE DOIT ÊTRE VISIBLE : Ajoute la balise <img src="URL_IMAGE" alt="..."> juste après le premier paragraphe. NE LA METS PAS en commentaire (<!-- -->).
4. Ne fais aucun commentaire textuel à la fin comme "Note : ...".
5. N'INCLUS PAS les posts sociaux.`;
}

// Ensure the Prepa Transfert cleans <!DOCTYPE html> just in case
let prepa = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepa) {
    prepa.parameters.jsCode = prepa.parameters.jsCode.replace(
        "let cleanText = rawText.replace(/\\`\\`\\`html\\\\n?/g, '').replace(/\\`\\`\\`\\\\n?/g, '').trim();",
        "let cleanText = rawText.replace(/\\`\\`\\`html\\\\n?/g, '').replace(/\\`\\`\\`\\\\n?/g, '').replace(/<\\!DOCTYPE html>\\s*<html[^>]*>\\s*<head>[\\s\\S]*?<\\/head>\\s*<body[^>]*>/i, '').replace(/<\\/body>\\s*<\\/html>/i, '').trim();"
    );
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Workflow updated to fix HTML document generation and image fallback.');
