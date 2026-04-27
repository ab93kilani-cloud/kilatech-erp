const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Architect Prompt with proper n8n expression syntax
let architect = nodes.find(n => n.name === 'Architect');
if (architect) {
    // Note the '=' at the beginning, it tells n8n this is an expression
    architect.parameters.prompt = `=Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la structure parfaite pour optimiser le produit médical suivant au Maroc.

--- PRODUIT RÉEL À TRAITER ---
NOM : {{ $json.product.name }}
DESCRIPTION : {{ $json.product.description }}
CATÉGORIE : {{ $json.product.category }}

--- ANALYSE TECHNIQUE (AUDIT & ESPION) ---
{{ $json.text }}

--- INSTRUCTIONS ---
1. UTILISE LE NOM DU PRODUIT ({{ $json.product.name }}) DANS LES TITRES.
2. TU DOIS RÉPONDRE UNIQUEMENT EN FORMAT JSON :
{
    "plan_h2": ["Titre avec {{ $json.product.name }}", "..."],
    "instructions_designer": "...",
    "mots_cles_experts": ["{{ $json.product.name }}", "..."],
    "meta_description": "..."
}

Ne fais pas d'introduction, réponds uniquement en JSON.`;
}

// Fix Redacteur Prompt as well
let redacteur = nodes.find(n => n.name === 'Redacteur');
if (redacteur) {
    redacteur.parameters.prompt = `=Tu es le Rédacteur Expert en équipement médical pour Kilatech.ma.

Ta mission est de rédiger une fiche produit COMPLÈTE pour le produit : {{ $node["PRODUIT"].json.name }}.

--- FICHE TECHNIQUE ---
{{ $node["PRODUIT"].json.description }}

--- PLAN DE L'ARCHITECTE ---
{{ $json.text }}

--- RÈGLES ---
1. Cite le nom {{ $node["PRODUIT"].json.name }} régulièrement.
2. Format HTML (h2, h3, p). PAS DE H1.

RÉDIGE LA FICHE ICI :`;
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Workflow prompts updated with proper n8n expression syntax.');
