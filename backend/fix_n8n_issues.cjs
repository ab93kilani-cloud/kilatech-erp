const fs = require('fs');

const path = 'backend/WORKFLOW_MASTER_KILATECH.json';
const content = fs.readFileSync(path, 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Update Dossier de Redaction Final to extract meta_desc from Architect
let dossier = nodes.find(n => n.name === 'Dossier de Redaction Final');
if (dossier) {
    dossier.parameters.jsCode = `const getSafe = (nodeName) => {
    try {
        const node = $(nodeName);
        if (node && node.first()) {
            return node.first().json.text || 'N/A';
        }
    } catch (e) {}
    return 'N/A';
};

// Get image URL directly from Auditor node
let imageUrl = 'N/A';
try {
    const auditor = $("1. L'Auditeur");
    if (auditor && auditor.first()) {
        imageUrl = auditor.first().json.product.main_image_url || auditor.first().json.product.images[0].url || 'N/A';
    }
} catch (e) {}

// Correction Maya : Proxy d'images pour contourner l'absence de lien symbolique
imageUrl = imageUrl.replace('https://app.kilatech.ma/storage/', 'https://app.kilatech.ma/api/storage_proxy.php?path=');
if(imageUrl === 'N/A' || !imageUrl.includes('http')) {
   imageUrl = 'https://app.kilatech.ma/api/storage_proxy.php?path=images/placeholder.jpg';
}

// Get Meta Desc from Architect
let metaDesc = 'N/A';
try {
   const archText = $('Architect').first().json.text;
   const archResult = typeof archText === 'string' ? JSON.parse(archText) : archText;
   metaDesc = archResult.meta_description || 'N/A';
} catch (e) {}

return {
    product_id: $('PRODUIT').first().json.id,
    product_name: $('PRODUIT').first().json.name,
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef'),
    image_url: imageUrl,
    meta_desc: metaDesc
};`;
}

// 2. Update Prepa Transfert to clean markdown and handle meta_desc
let prepa = nodes.find(n => n.name === '📦 Prepa Transfert');
if (prepa) {
    prepa.parameters.jsCode = `let rawText = items[0].json.output[0].content[0].text;

// Clean markdown wrappers
let cleanText = rawText.replace(/\`\`\`html\\n?/g, '').replace(/\`\`\`\\n?/g, '').trim();

// Strip any social posts if they sneak in
const socialIndex = cleanText.indexOf('<h3>Promotion Sociale</h3>');
if (socialIndex !== -1) {
    cleanText = cleanText.substring(0, socialIndex).trim();
}
// Strip horizontal rule sometimes added before social posts
if(cleanText.endsWith('<hr>')) {
    cleanText = cleanText.substring(0, cleanText.length - 4).trim();
}

const dossierNode = $('Dossier de Redaction Final').first().json;
const productId = dossierNode.product_id;
const cmText = dossierNode.cm;
const metaDesc = dossierNode.meta_desc;

return {
  product_id: productId,
  seo_content: cleanText,
  seo_description: metaDesc,
  seo_score: Math.min(95, 40 + Math.floor(cleanText.length / 50)),
  social_linkedin_post: cmText.split('Post LinkedIn :')[1]?.split('Post Facebook :')[0]?.trim() || 'N/A',
  social_facebook_post: cmText.split('Post Facebook :')[1]?.split('Hashtags :')[0]?.trim() || 'N/A'
};`;
}

// 3. Update Le Directeur to send seo_description
let directeur = nodes.find(n => n.name === '🏢 11. Le Directeur');
if (directeur) {
    // Check if seo_description already exists
    let hasMeta = directeur.parameters.bodyParameters.parameters.find(p => p.name === 'seo_description');
    if (!hasMeta) {
        directeur.parameters.bodyParameters.parameters.push({
            name: 'seo_description',
            value: '={{ $json.seo_description }}'
        });
    }
}

fs.writeFileSync(path, JSON.stringify(wf));
console.log('SUCCESS: Workflow updated to fix markdown, meta_desc, and image fallback.');
