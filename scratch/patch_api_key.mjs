import fs from 'fs';

const paths = [
  'backend/WORKFLOW_KILATECH_V2_ECONOMY.json',
  'backend/WORKFLOW_MASTER_KILATECH.json'
];

paths.forEach(path => {
  if (fs.existsSync(path)) {
    let content = fs.readFileSync(path, 'utf8');
    content = content.replace(/KILATECH_7382/g, 'KILA-SEO-PROD-2026-X9');
    fs.writeFileSync(path, content);
    console.log(`✅ Updated API Key in ${path}`);
  }
});
