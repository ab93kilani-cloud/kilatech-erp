import fs from 'fs';

const filePath = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(filePath, 'utf8');
const workflowArray = JSON.parse(content);
const nodes = workflowArray[0].nodes;

// 1. Fix Architect Node (id: c88e1f3b-ac04-42a1-9712-5cacc8777fa3)
const architect = nodes.find(n => n.id === 'c88e1f3b-ac04-42a1-9712-5cacc8777fa3');
if (architect) {
    architect.parameters.prompt = "=Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la structure parfaite pour optimiser le produit médical suivant au Maroc.\n\n--- PRODUIT RÉEL À TRAITER ---\nNOM : {{ $json.product.name }}\nDESCRIPTION : {{ $json.product.description }}\nCATÉGORIE : {{ $json.product.category }}\n\n--- ANALYSE TECHNIQUE (AUDIT & ESPION) ---\n{{ $json.text }}\n\n--- INSTRUCTIONS ---\n1. UTILISE LE NOM DU PRODUIT ({{ $json.product.name }}) DANS LES TITRES.\n2. AJOUTE TOUJOURS UN CHAMP 'h1_title' OPTIMISÉ POUR CASABLANCA ET LE MAROC.\n3. TU DOIS RÉPONDRE UNIQUEMENT EN FORMAT JSON :\n{\n    \"h1_title\": \"Exemple : Matériel Médical Casablanca - {{ $json.product.name }} Haute Qualité\",\n    \"plan_h2\": [\"Titre avec {{ $json.product.name }}\", \"...\"],\n    \"instructions_designer\": \"...\",\n    \"mots_cles_experts\": [\"{{ $json.product.name }}\", \"...\"],\n    \"meta_description\": \"... (150 caractères maximum, pas de HTML)\"\n}\n\nNe fais pas d'introduction, réponds uniquement en JSON.";
}

// 2. Fix Le Finaliseur Node (id: 89ceec68-5d2b-41af-af1a-d59f0a934fca)
const finaliseur = nodes.find(n => n.id === '89ceec68-5d2b-41af-af1a-d59f0a934fca');
if (finaliseur) {
    const systemMessage = finaliseur.parameters.responses.values.find(v => v.role === 'system');
    if (systemMessage) {
        systemMessage.content = "=Tu es l'Intégrateur Web de Kilatech.ma.\n\nTEXTE : {{ $node[\"Dossier de Redaction Final\"].json.tisseur }}\n\nINSTRUCTIONS ULTRA STRICTES :\n1. NE GÉNÈRE AUCUNE BALISE <img>. Le site gère déjà l'image principale.\n2. Tu DOIS renvoyer UNIQUEMENT les paragraphes et les titres.\n3. INTERDICTION ABSOLUE de générer <!DOCTYPE html>, <html>, <head> ou <body>.\n4. INTERDICTION ABSOLUE d'utiliser des blocs markdown (```html).\n5. N'ajoute pas les posts sociaux à la fin.\n6. Utilise uniquement <h2>, <h3>, <p>, <ul> et <li>.";
    }
}

// 3. Fix Prepa Transfert Node (id: master-prepa-id)
const prepa = nodes.find(n => n.id === 'master-prepa-id');
if (prepa) {
    prepa.parameters.jsCode = `let rawText = items[0].json.output[0].content[0].text;
const dossierNode = $('Dossier de Redaction Final').first().json;

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
let cleanText = rawText.replace(/\\\`\\\`\\\`html\\n?/g, '').replace(/\\\`\\\`\\\`\\n?/g, '').trim();

// 4. Strip any social posts if they leaked
const socialIndex = cleanText.indexOf('<h3>Promotion Sociale</h3>');
if (socialIndex !== -1) {
    cleanText = cleanText.substring(0, socialIndex).trim();
}
if(cleanText.endsWith('<hr>')) {
    cleanText = cleanText.substring(0, cleanText.length - 4).trim();
}

// 5. Clean Meta Description (No HTML, No double quotes)
let cleanMeta = dossierNode.meta_desc || '';
cleanMeta = cleanMeta.replace(/<[^>]*>/g, '').replace(/"/g, "'").trim();
if (cleanMeta.length > 160) cleanMeta = cleanMeta.substring(0, 157) + '...';

return {
  product_id: dossierNode.product_id,
  product_name: dossierNode.product_name,
  seo_content: cleanText.trim(),
  seo_description: cleanMeta,
  seo_score: Math.min(95, 40 + Math.floor(cleanText.length / 50)),
  social_linkedin_post: dossierNode.cm.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: dossierNode.cm.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

// 4. Fix Director Node (id: director-node-id) to include the H1 Title
const director = nodes.find(n => n.id === 'director-node-id');
if (director) {
    // Add seo_title mapping from Architect's h1_title
    const params = director.parameters.bodyParameters.parameters;
    if (!params.find(p => p.name === 'seo_title')) {
        params.push({
            name: "seo_title",
            value: "={{ $node[\"Dossier de Redaction Final\"].json.h1_title || $node[\"PRODUIT\"].json.name }}"
        });
    }
}

// 5. Update Dossier de Redaction Final (id: 7e09ca55-1ea0-4061-b667-b62c126be3ec) to extract h1_title
const dossier = nodes.find(n => n.id === '7e09ca55-1ea0-4061-b667-b62c126be3ec');
if (dossier) {
    dossier.parameters.jsCode = dossier.parameters.jsCode.replace(
        "meta_desc: metaDesc",
        "meta_desc: metaDesc,\n    h1_title: h1Title"
    );
    // Add logic to extract h1Title
    dossier.parameters.jsCode = dossier.parameters.jsCode.replace(
        "metaDesc = archResult.meta_description || 'N/A';",
        "metaDesc = archResult.meta_description || 'N/A';\n   h1Title = archResult.h1_title || 'N/A';"
    );
    // Add variable declaration
    dossier.parameters.jsCode = "let h1Title = 'N/A';\n" + dossier.parameters.jsCode;
}

fs.writeFileSync(filePath, JSON.stringify(workflowArray, null, 2));
console.log('✅ Workflow Master updated successfully with Phase 1 fixes.');
