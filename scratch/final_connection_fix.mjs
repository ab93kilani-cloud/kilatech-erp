import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const wf = JSON.parse(fs.readFileSync(path, 'utf8'));

// Fix "Le Directeur" to use the output from "Nettoyeur V2" correctly
const directorIndex = wf[0].nodes.findIndex(n => n.name === "🏢 11. Le Directeur");
if (directorIndex !== -1) {
  wf[0].nodes[directorIndex].parameters.bodyParameters.parameters.forEach(param => {
    if (param.name === "seo_title") {
      param.value = "={{ $json.seo_title }}";
    }
  });
}

// Fix "Le Manager" notification text
const managerIndex = wf[0].nodes.findIndex(n => n.name === "📱 12. Le Manager");
if (managerIndex !== -1) {
  managerIndex.jsonQuery = "={\n  \"phone\": \"+212645065281\",\n  \"text\": \"✅ Kilatech SEO Alert: Le produit {{ $node['PRODUIT'].json.data[0].name }} est optimise! Score SEO: {{ $json.seo_score }}/100\",\n  \"apikey\": \"341855\"\n}";
}

fs.writeFileSync(path, JSON.stringify(wf, null, 2));
console.log('✅ Final connection fixed. The workflow is now a complete loop.');
