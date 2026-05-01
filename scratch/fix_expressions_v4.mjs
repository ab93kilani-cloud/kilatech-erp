import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const wf = JSON.parse(fs.readFileSync(path, 'utf8'));

wf[0].nodes.forEach(node => {
  if (node.name === "🕵️ 1. L'Auditeur") {
    node.parameters.url = "=https://app.kilatech.ma/api/seo-v2/audit/{{ $node[\"PRODUIT\"].json.data[0].id }}?api_token=KILA-SEO-PROD-2026-X9";
  }
  if (node.name === "Espion") {
    node.parameters.jsonBody = "={{ JSON.stringify({ q: ($node[\"PRODUIT\"].json.data[0].name || 'Produit') + ' prix Maroc', gl: 'ma' }) }}";
  }
  if (node.name === "🚀 Super Agent SEO") {
    node.parameters.prompt = "=Tu es l'Intelligence SEO Suprême de Kilatech.ma.\n\nPRODUIT : {{ $node[\"PRODUIT\"].json.data[0].name }}\nINFOS MARCHÉ : {{ JSON.stringify($json.organic || []) }}\n\nMISSION :\n1. Rédige une fiche produit HTML premium (H2, H3, P).\n2. Intègre naturellement 2-3 liens vers des catégories médicales.\n3. Crée un Titre H1 puissant incluant 'Casablanca' ou 'Maroc'.\n4. Crée une Meta-Description de 150 caractères.\n5. Crée un post LinkedIn et un post Facebook.\n\nCONSIGNES ULTRA-STRICTES :\n- AUCUNE balise <img>.\n- AUCUN bloc markdown (pas de ```html).\n- AUCUN conseil technique dans le texte.\n- Langue : Français parfait ('au Maroc').\n\nRÉPONDS UNIQUEMENT EN JSON :\n{\n  \"h1_title\": \"...\",\n  \"meta_description\": \"...\",\n  \"html_content\": \"...\",\n  \"linkedin_post\": \"...\",\n  \"facebook_post\": \"...\"\n}";
  }
  if (node.name === "📦 Nettoyeur V2") {
    node.parameters.jsCode = "const data = $json;\nreturn {\n  product_id: $('PRODUIT').item.json.data[0].id,\n  seo_title: data.h1_title,\n  seo_description: data.meta_description,\n  seo_content: data.html_content,\n  social_linkedin_post: data.linkedin_post,\n  social_facebook_post: data.facebook_post,\n  seo_score: 95\n};";
  }
  if (node.name === "📱 12. Le Manager") {
    node.parameters.jsonQuery = "={\n  \"phone\": \"+212645065281\",\n  \"text\": \"✅ Kilatech SEO Alert: Le produit {{ $node['PRODUIT'].json.data[0].name }} (ID: {{ $node['PRODUIT'].json.data[0].id }}) est optimise! Score SEO: {{ $node['🏢 11. Le Directeur'].json.seo_score }}/100\",\n  \"apikey\": \"341855\"\n}";
  }
});

fs.writeFileSync(path, JSON.stringify(wf, null, 2));
console.log('✅ Workflow expressions fixed safely via Object manipulation.');
