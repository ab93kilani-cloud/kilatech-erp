import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: {
      'X-N8N-API-KEY': API_KEY,
      'Content-Type': 'application/json'
    }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  try {
    const masterId = "Rbx87xuCY2i1VVBQ";
    let wf = await request(`/workflows/${masterId}`, 'GET');
    
    console.log("Installing the Strategic Decision Module (Growing Agents)...");

    // 1. Data Aggregator (Code Node)
    const aggregator = {
      parameters: {
        jsCode: "const results = items[0].json.rows || [];\nlet table = '| URL | Clicks | Impressions | CTR | Position |\\n|---|---|---|---|---|\\n';\nresults.slice(0, 10).forEach(row => {\n  table += `| ${row.keys[0]} | ${row.clicks} | ${row.impressions} | ${(row.ctr * 100).toFixed(2)}% | ${row.position.toFixed(1)} |\\n`;\n});\nreturn { table };"
      },
      name: "Tableau de Performance GSC",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1600, 500]
    };

    // 2. Vulnerability Detector (AI)
    const detector = {
      parameters: {
        model: "gpt-3.5-turbo-instruct",
        prompt: "=Tu es l'Analyste de Performance Kilatech.\\n\\nVOICI LE TABLEAU DES RÉSULTATS GOOGLE :\\n{{ $json.table }}\\n\\nMISSION :\\n1. Identifie les 3 produits qui ont le plus baissé ou qui ont un CTR trop faible (clics/impressions).\\n2. Dis pourquoi selon toi (Titre pas assez attirant ? Position trop basse ?).\\n3. Présente cela comme un rapport d'anomalies.",
        options: { maxTokens: 2000 }
      },
      name: "Détecteur de Failles GSC",
      type: "n8n-nodes-base.openAi",
      typeVersion: 1,
      position: [1800, 500],
      credentials: wf.nodes.find(n => n.name.includes("Architect"))?.credentials
    };

    // 3. Strategic SEO Manager (AI Decision Maker)
    const strategyManager = {
      parameters: {
        model: "gpt-3.5-turbo-instruct",
        prompt: "=Tu es le Directeur Stratégique SEO de Kilatech.ma.\\n\\nRAPPORT D'ANOMALIES :\\n{{ $json.text }}\\n\\nMISSION :\\nPrend des décisions fermes pour chaque problème identifié.\\nExemple de réponse :\\n- [Produit X] : Relancer le workflow avec de nouveaux mots-clés.\\n- [Produit Y] : Demander au Designer de rendre le texte plus aéré.\\n- [Produit Z] : Alerte au patron, la concurrence nous dépasse sur ce secteur.",
        options: { maxTokens: 2000 }
      },
      name: "Stratège Kilatech",
      type: "n8n-nodes-base.openAi",
      typeVersion: 1,
      position: [2000, 500],
      credentials: wf.nodes.find(n => n.name.includes("Architect"))?.credentials
    };

    // Add nodes to workflow
    [aggregator, detector, strategyManager].forEach(node => {
        if (!wf.nodes.find(n => n.name === node.name)) {
            wf.nodes.push(node);
        }
    });

    // Connect from Fetch GSC Analytics to Table
    wf.connections["GSC - Fetch GSC Analytics"] = wf.connections["GSC - Fetch GSC Analytics"] || { main: [] };
    wf.connections["GSC - Fetch GSC Analytics"].main.push([{ node: "Tableau de Performance GSC", type: "main", index: 0 }]);
    
    // Connect Table -> Detector -> Strategist
    wf.connections["Tableau de Performance GSC"] = { main: [[{ node: "Détecteur de Failles GSC", type: "main", index: 0 }]] };
    wf.connections["Détecteur de Failles GSC"] = { main: [[{ node: "Stratège Kilatech", type: "main", index: 0 }]] };

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Decision Module installed. Your SEO brain is now strategic.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
