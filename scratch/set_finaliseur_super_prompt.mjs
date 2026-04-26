

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const MASTER_WF_ID = 'Rbx87xuCY2i1VVBQ';

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
    console.log("Setting the Ultimate Synthesis Prompt for Le Finaliseur...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finaliseurName = "Le Finaliseur";
    const finaliseur = wf.nodes.find(n => n.name === finaliseurName);

    if (finaliseur) {
        // We ensure it uses the structured data from the named aggregator
        finaliseur.parameters.messages = {
            messageValues: [
                {
                    role: "system",
                    content: "Tu es le Directeur de Publication et Expert SEO de Kilatech.ma. Ta mission est de produire le contenu FINAL d'une fiche produit d'excellence."
                },
                {
                    role: "user",
                    content: "=VOICI LE DOSSIER COMPLET DE LA RÉDACTION :\n\n- PRODUIT : {{ $json.nom_produit }}\n- CONTENU BRUT : {{ JSON.stringify($json.details_agents.redacteur) }}\n- DESIGN & IMAGES : {{ JSON.stringify($json.details_agents.designer) }}\n- CRITIQUES RÉDACTEUR CHEF : {{ JSON.stringify($json.details_agents.redacteur_chef) }}\n- OPTIMISATIONS ANALYSTE : {{ JSON.stringify($json.details_agents.analyste) }}\n- POSTS SOCIAUX : {{ JSON.stringify($json.details_agents.community_manager) }}\n\nMISSION DE SYNTHÈSE :\n1. Rédige un titre SEO captivant et une méta-description qui pousse au clic.\n2. Produis le corps de la page en HTML sémantique (H1, H2, p, strong).\n3. APPLIQUE impérativement les corrections du Rédacteur en Chef pour supprimer les fautes ou améliorer le ton.\n4. INTÈGRE les mots-clés suggérés par l'Analyste pour maximiser le score SEO.\n5. AJOUTE à la fin une section <hr><h3>PROMOTION SOCIALE</h3> avec les posts LinkedIn et Facebook.\n\nFORMAT DE SORTIE :\nRenvoie uniquement le code HTML complet, prêt à être copié."
                }
            ]
        };
        console.log("Finaliseur prompt updated with strategic instructions.");
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("The Finaliseur is now a Master Editor!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
