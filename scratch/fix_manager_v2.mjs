import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const wf = JSON.parse(fs.readFileSync(path, 'utf8'));

const managerIndex = wf[0].nodes.findIndex(n => n.name === "📱 12. Le Manager");
if (managerIndex !== -1) {
  wf[0].nodes[managerIndex].parameters.jsonQuery = "={\n  \"phone\": \"+212645065281\",\n  \"text\": \"✅ Kilatech SEO Alert: Le produit {{ $node['PRODUIT'].json.data[0].name }} est optimise! Score SEO: {{ $json.seo_score }}/100\",\n  \"apikey\": \"341855\"\n}";
}

fs.writeFileSync(path, JSON.stringify(wf, null, 2));
console.log('✅ Manager notification fixed.');
