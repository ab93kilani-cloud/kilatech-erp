const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Fix Architect Prompt
let architect = nodes.find(n => n.name === 'Architect');
if (architect) {
    architect.parameters.prompt = `Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la structure parfaite pour optimiser le produit médical suivant au Maroc.

--- PRODUIT RÉEL À TRAITER ---
NOM : {{ $('PRODUIT').first().json.name }}
DESCRIPTION : {{ $('PRODUIT').first().json.description }}
CATÉGORIE : {{ $('PRODUIT').first().json.category }}

--- ANALYSE TECHNIQUE (AUDIT & ESPION) ---
{{ $json.text }}

--- INSTRUCTIONS ---
TU DOIS RÉPONDRE UNIQUEMENT EN FORMAT JSON AVEC CES 4 SECTIONS :
1. "plan_h2" : Liste des titres optimisés (Commence par un H2, puis H3). L'utilisation du H1 est STRICTEMENT INTERDITE.
2. "instructions_designer" : Comment mettre en forme le texte pour ce produit spécifique.
3. "mots_cles_experts" : Liste des mots techniques à utiliser.
4. "meta_description" : La méta-description recommandée.

Ne fais pas de phrases d'introduction, réponds uniquement en JSON.`;
}

// 2. Fix Redacteur Prompt
let redacteur = nodes.find(n => n.name === 'Redacteur');
if (redacteur) {
    redacteur.parameters.prompt = `=Tu es le Rédacteur Expert en équipement médical pour Kilatech.ma.

Ta mission est de rédiger une fiche produit COMPLÈTE, PERSUASIVE et OPTIMISÉE SEO pour le produit : {{ $('PRODUIT').first().json.name }}.

--- FICHE TECHNIQUE ORIGINALE ---
{{ $('PRODUIT').first().json.description }}

--- PLAN DE L'ARCHITECTE (JSON) ---
{{ $json.text }}

--- RÈGLES DE RÉDACTION ---
1. Utilise les titres du plan_h2.
2. Développe des paragraphes riches et techniques.
3. Intègre de façon fluide les "mots_cles_experts".
4. FORMATAGE : Réponds uniquement en HTML (<h2>, <h3>, <p>, <ul>, <li>). PAS DE H1.

RÉDIGE LA FICHE PRODUIT FINALE ICI (en restant fidèle au produit {{ $('PRODUIT').first().json.name }}) :`;
}

// 3. Fix Designer Prompt
let designer = nodes.find(n => n.name === '🎨 5. Le Designer');
if (designer) {
    designer.parameters.messages.values[1].content = `=DONNÉES : {{ $json.text }}
NOM PRODUIT : {{ $('PRODUIT').first().json.name }}
URL IMAGE : {{ $("PRODUIT").first().json.images[0]?.url || "https://api.kilatech.ma/storage/placeholder.jpg" }}

CONSIGNES :
1. Crée une balise ALT SEO parfaite pour le produit : {{ $('PRODUIT').first().json.name }}.
2. Définis le style CSS Premium.
3. Instructions de mise en page.

Réponds UNIQUEMENT en JSON : {"image_alt": "...", "css_style": "...", "layout_instructions": "..."}`;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Architect, Redacteur and Designer prompts updated with hardcoded product references.');
