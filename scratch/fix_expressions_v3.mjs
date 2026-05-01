import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
let content = fs.readFileSync(path, 'utf8');

// Use a more robust approach: Replace the whole lines to avoid quote escaping hell
content = content.replace(
  /"url": "=https:\/\/app\.kilatech\.ma\/api\/seo-v2\/audit\/.*",/g,
  '"url": "=https://app.kilatech.ma/api/seo-v2/audit/{{ $node[\\"PRODUIT\\"].json.data[0].id }}?api_token=KILA-SEO-PROD-2026-X9",'
);

content = content.replace(
  /"jsonBody": "=.*prix Maroc.*",/g,
  '"jsonBody": "={{ JSON.stringify({ q: ($node[\\"PRODUIT\\"].json.data[0].name || \\"Produit\\") + \\" prix Maroc\\", gl: \\"ma\\" }) }}",'
);

content = content.replace(
  /"prompt": "=Tu es l'Intelligence SEO Suprême de Kilatech\.ma.* mission/si,
  '"prompt": "=Tu es l\'Intelligence SEO Suprême de Kilatech.ma.\\n\\nPRODUIT : {{ $node[\\"PRODUIT\\"].json.data[0].name }}\\nINFOS MARCHÉ : {{ JSON.stringify($json.organic || []) }}\\n\\nMISSION'
);

content = content.replace(
  /Le produit .* ID: .*\!/g,
  'Le produit {{ $node[\\"PRODUIT\\"].json.data[0].name }} (ID: {{ $node[\\"PRODUIT\\"].json.data[0].id }}) est optimise!'
);

fs.writeFileSync(path, content);
console.log('✅ Workflow expressions fixed with proper escaping.');
