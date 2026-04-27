const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

let finaliseur = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseur) {
    finaliseur.parameters.responses.values[0].content = `=Tu es l'Intégrateur Web de Kilatech.ma.
MISSION : Tu dois générer le code HTML FINAL de l'article.

DONNÉES À TA DISPOSITION :
1. TEXTE FINAL : {{ $node["Dossier de Redaction Final"].json.tisseur }}
2. URL IMAGE : {{ $node["Dossier de Redaction Final"].json.image_url }}
3. CONSEILS DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}

RÈGLES ABSOLUES DE FORMATAGE :
1. AUCUN BLOC MARKDOWN. Ne commence pas par \`\`\`html et ne termine pas par \`\`\`. Renvoie UNIQUEMENT le code HTML pur.
2. N'INCLUS PAS les posts sociaux (LinkedIn, Facebook). Ton travail est uniquement sur le contenu de la page web.
3. L'IMAGE DOIT ÊTRE VISIBLE : Ajoute la balise <img src="URL_IMAGE" alt="..."> juste après le premier paragraphe. NE LA METS PAS en commentaire (<!-- -->).
4. Ne fais aucun commentaire textuel à la fin comme "Note : ...".
5. Si l'URL de l'image est "N/A", utilise l'image par défaut : "https://app.kilatech.ma/api/storage_proxy.php?path=images/placeholder.jpg".`;

    // The user message should just say "Commence." or we can remove it, but n8n langchain node requires messages
    finaliseur.parameters.responses.values[1].content = `Génère le HTML final maintenant. N'oublie pas : aucun markdown \`\`\`html, et pas de posts sociaux.`;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Finaliseur node prompt updated.');
