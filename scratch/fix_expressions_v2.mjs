import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
let content = fs.readFileSync(path, 'utf8');

// Fix L'Auditeur URL
content = content.replace(
  /seo-v2\/audit\/\{\{ \$node\[\\"PRODUIT\\"\]\.json\.id \}\}/g,
  'seo-v2/audit/{{ $node["PRODUIT"].json.data[0].id }}'
);

// Fix Espion
content = content.replace(
  /\(\$node\[\\"PRODUIT\\"\]\.json\.name \|\| 'Produit'\)/g,
  '($node["PRODUIT"].json.data[0].name || "Produit")'
);

// Fix Super Agent SEO
content = content.replace(
  /PRODUIT : \{\{ \$node\[\\"PRODUIT\\"\]\.json\.name \}\}/g,
  'PRODUIT : {{ $node["PRODUIT"].json.data[0].name }}'
);

// Fix Nettoyeur V2 JS Code
content = content.replace(
  /\$\('PRODUIT'\)\.first\(\)\.json\.id/g,
  '$(\'PRODUIT\').item.json.data[0].id'
);

// Fix Le Directeur Title
content = content.replace(
  /\{\{ \$node\[\\"Dossier de Redaction Final\\"\]\.json\.h1_title \|\| \$node\[\\"PRODUIT\\"\]\.json\.name \}\}/g,
  '{{ $json.seo_title }}'
);

// Fix Le Manager ID
content = content.replace(
  /Le produit \{\{ \$node\['PRODUIT'\]\.json\.name \}\} \(ID: \{\{ \$node\['PRODUIT'\]\.json\.id \}\}\)/g,
  'Le produit {{ $node["PRODUIT"].json.data[0].name }} (ID: {{ $node["PRODUIT"].json.data[0].id }})'
);

fs.writeFileSync(path, content);
console.log('✅ Workflow expressions fixed for n8n Batch v3 compatibility.');
