<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketingAiService
{
    protected $apiKey;
    protected $apiUrl = 'https://api.openai.com/v1/chat/completions'; // Default to OpenAI

    public function __construct()
    {
        $this->apiKey = config('services.marketing_ai.api_key');
    }

    /**
     * Generate all marketing content for a product
     */
    public function generateForProduct($product)
    {
        if (empty($this->apiKey)) {
            Log::warning('Marketing AI API Key not set. Returning mock data.');
            return $this->getMockData($product);
        }

        try {
            $seo = $this->generateSeo($product);
            $social = $this->generateSocial($product);
            $geo = $this->generateGeo($product);

            return [
                'slug' => $seo['slug'] ?? Str::slug($product->name),
                'meta_title' => $seo['meta_title'] ?? $product->name,
                'meta_description' => $seo['meta_description'] ?? '',
                'seo_alt' => $seo['seo_alt'] ?? "Photo " . $product->name . " - Kilatech Maroc",
                'meta_keywords' => $seo['focus_keywords'] ?? '',
                'social_linkedin_post' => $social['linkedin_post'] ?? '',
                'social_facebook_post' => $social['facebook_post'] ?? '',
                'social_instagram_post' => $social['instagram_post'] ?? '',
                'local_geo_context' => $geo['local_context'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Marketing AI Generation failed: ' . $e->getMessage());
            return $this->getMockData($product);
        }
    }

    protected function generateSeo($product)
    {
        $prompt = "Tu es un Expert SEO Senior spécialisé dans le B2B Médical au Maroc. 
        
        CONSIGNE CRITIQUE : Le titre SEO (meta_title) DOIT impérativement commencer par le nom du produit '{$product->name}'.
        
        Produit: {$product->name}
        Description: {$product->description}
        Catégorie: {$product->category}
        
        Génère un JSON avec: 
        - meta_title (max 60 chars, COMMENCE obligatoirement par '{$product->name}'), 
        - meta_description (max 155 chars, doit être percutante et inclure 'Maroc'), 
        - seo_alt (max 100 chars, description pour Google Images, ex: '{$product->name} disponible chez Kilatech Maroc'),
        - slug (url friendly), 
        - focus_keywords (5 mots-clés séparés par virgules).";

        return $this->callAi($prompt);
    }

    protected function generateSocial($product)
    {
        $prompt = "Tu es un Copywriter B2B expert en LinkedIn et Facebook pour le secteur médical au Maroc.
        Produit: {$product->name}
        Description: {$product->description}
        
        Génère un JSON avec: linkedin_post (professionnel, technique), facebook_post (chaleureux, emojis), instagram_post (visuel, engageant, hashtags inclus), hashtags (liste de 5 pour LinkedIn/FB).";

        return $this->callAi($prompt);
    }

    protected function generateGeo($product)
    {
        $prompt = "Tu es un stratège en référencement local (Local SEO) au Maroc.
        Produit: {$product->name}
        
        Génère un JSON avec: local_context (phrase liant le produit aux grandes villes marocaines), geo_keywords (mots-clés avec villes).";

        return $this->callAi($prompt);
    }

    protected function callAi($prompt)
    {
        $response = Http::withToken($this->apiKey)
            ->post($this->apiUrl, [
                'model' => 'gpt-3.5-turbo', // Or gpt-4
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un expert marketing. Réponds UNIQUEMENT au format JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object']
            ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content') ? json_decode($response->json('choices.0.message.content'), true) : [];
        }

        throw new \Exception('AI API call failed: ' . $response->body());
    }

    public function generateArticleForProduct($product)
    {
        return [
            'title' => "Tout savoir sur le " . $product->name . " : Guide d'achat et maintenance",
            'slug' => Str::slug("guide-tout-savoir-" . $product->name),
            'content' => "
                <h2>Introduction au " . $product->name . "</h2>
                <p>Le <strong>" . $product->name . "</strong> est devenu un outil indispensable dans les établissements de santé modernes au Maroc. Que vous soyez à Casablanca, Rabat ou Tanger, la qualité de votre matériel biomédical définit la qualité de vos soins.</p>
                
                <h3>Pourquoi choisir le " . $product->name . " ?</h3>
                <p>Ce dispositif, appartenant à la catégorie <strong>" . $product->category . "</strong>, offre des performances de pointe. Il a été conçu pour répondre aux normes les plus strictes de sécurité et d'efficacité.</p>
                
                <h3>L'expertise Kilatech pour votre " . $product->name . "</h3>
                <p>Chez Kilatech, nous ne nous contentons pas de vendre le matériel. Nous accompagnons nos clients avec :</p>
                <ul>
                    <li>Une installation conforme aux normes internationales</li>
                    <li>Une formation approfondie de votre équipe médicale</li>
                    <li>Un contrat de maintenance préventive pour éviter toute interruption</li>
                </ul>
                
                <h3>Conclusion</h3>
                <p>Investir dans un " . $product->name . " de haute qualité est crucial pour la pérennité de votre cabinet ou clinique. Contactez nos experts pour un devis personnalisé.</p>
            ",
            'meta_title' => "Guide " . $product->name . " | Kilatech Blog Médical",
            'meta_description' => "Le guide complet sur le " . $product->name . ". Découvrez ses avantages, son utilisation et comment Kilatech assure sa maintenance au Maroc.",
            'product_id' => $product->id,
            'status' => 'draft',
            'category' => 'blog',
        ];
    }

    public function generateNewsArticle($notes)
    {
        $prompt = "Tu es le Directeur de la Rédaction de Kilatech, une entreprise leader dans la technologie médicale B2B au Maroc. Ton style est inspiré des grands journaux économiques comme L'Économiste.
        
        MISSION : Rédiger une ANALYSE DE FOND exhaustive à partir des informations suivantes :
        Notes : \"{$notes}\"
        
        EXIGENCE DE QUALITÉ ÉLITE :
        1. LONGUEUR : L'article doit être long et détaillé (minimum 1000 mots). Ne sois pas concis, sois ANALYTIQUE.
        2. STRUCTURE HTML : Utilise <h2>, <h3>, <ul>, <li> et <p> pour une mise en page riche et aérée.
        3. CONTENU : Cadre l'actualité dans le domaine de la santé au Maroc, analyse les bénéfices techniques et inclus une citation d'expert Kilatech.
        
        RÉPONDRE UNIQUEMENT EN JSON AVEC : title, content (HTML), meta_title, meta_description, slug.";

        if (empty($this->apiKey)) {
            Log::warning('MARKETING_AI_API_KEY is missing. Using premium mock generator.');
            
            $date = date('d/m/Y');
            $title = "Révolution Médicale au Maroc : Kilatech franchit une étape décisive";
            
            return [
                'title' => $title,
                'slug' => Str::slug($title) . "-" . time(),
                'content' => "
                    <p><em>Casablanca, le $date</em> — <strong>Kilatech</strong> s'impose comme le catalyseur de l'excellence biomédicale au Maroc. L'annonce concernant <strong>\"$notes\"</strong> marque un tournant stratégique majeur.</p>
                    <h2>Une Vision Stratégique pour le Secteur Hospitalier</h2>
                    <p>L'engagement de Kilatech ne se limite pas à la fourniture d'équipements de pointe. Il s'agit d'intégrer les dernières innovations mondiales au cœur des cliniques marocaines. Cette avancée répond aux défis de précision auxquels font face les praticiens.</p>
                    <ul>
                        <li><strong>Fiabilité Technique :</strong> Des diagnostics plus sûrs grâce à une ingénierie de précision.</li>
                        <li><strong>Accompagnement Premium :</strong> Formation certifiante pour les équipes biomédicales.</li>
                    </ul>
                    <h2>Leadership et Innovation : Le Regard de nos Experts</h2>
                    <p>La direction technique de Kilatech souligne : <em>'Nous adaptons l'innovation internationale aux réalités du terrain marocain pour garantir une continuité des soins sans compromis.'</em></p>
                    <p>En investissant dans le Service Après-Vente (SAV) et la formation continue, Kilatech assure une pérennité maximale aux investissements de ses partenaires hospitaliers.</p>
                ",
                'meta_title' => $title . " | Actualités Kilatech",
                'meta_description' => "Analyse approfondie de Kilatech sur l'évolution du secteur médical au Maroc à travers : $notes.",
                'status' => 'draft',
                'category' => 'news',
            ];
        }

        try {
            $data = $this->callAi($prompt);
            return [
                'title' => $data['title'] ?? "Actualité Stratégique Kilatech",
                'slug' => $data['slug'] ?? Str::slug($data['title'] ?? 'actu-'.time()),
                'content' => $data['content'] ?? "<p>{$notes}</p>",
                'meta_title' => $data['meta_title'] ?? "Actualité | Kilatech",
                'meta_description' => $data['meta_description'] ?? "",
                'status' => 'draft',
                'category' => 'news',
            ];
        } catch (\Exception $e) {
            Log::error('AI News Generation Failed: ' . $e->getMessage());
            $this->apiKey = null; 
            return $this->generateNewsArticle($notes);
        }
    }

    public function generateNewsForCategory($categoryName)
    {
        $prompt = "Tu es Expert en Santé Publique au Maroc et Directeur Éditorial chez Kilatech.
        MISSION : Rédiger un article d'actualité de haute expertise sur l'innovation dans la famille d'équipement : \"{$categoryName}\".
        
        CONTEXTE : Le Maroc modernise son système de santé (Généralisation de l'AMO, nouveaux hôpitaux).
        STRUCTURE : 
        1. État des lieux de la technologie {$categoryName} au Maroc.
        2. Pourquoi Kilatech est le partenaire idéal pour ces équipements.
        3. Futur et perspectives.
        
        Format : JSON avec title, content (HTML riche), meta_title, meta_description, slug.";

        if (empty($this->apiKey)) {
            $title = "L'Innovation dans le domaine : $categoryName au Maroc";
            return [
                'title' => $title,
                'slug' => Str::slug($title) . "-" . time(),
                'content' => "<h2>Le secteur de $categoryName en pleine mutation</h2><p>Le marché marocain de l'équipement médical, et plus particulièrement la famille <strong>$categoryName</strong>, connaît une croissance sans précédent...</p><h3>L'accompagnement Kilatech</h3><p>Fort de son expertise, Kilatech propose des solutions adaptées...</p>",
                'meta_title' => "$categoryName Maroc | Actualités Kilatech",
                'meta_description' => "Découvrez les dernières tendances et innovations sur les équipements de type $categoryName au Maroc.",
                'status' => 'draft',
                'category' => 'news',
            ];
        }

        try {
            $data = $this->callAi($prompt);
            return [
                'title' => $data['title'] ?? "Actualité Stratégique Kilatech",
                'slug' => $data['slug'] ?? Str::slug($data['title'] ?? 'actu-'.time()),
                'content' => $data['content'] ?? "<p>{$notes}</p>",
                'meta_title' => $data['meta_title'] ?? "Actualité | Kilatech",
                'meta_description' => $data['meta_description'] ?? "",
                'status' => 'draft',
                'category' => 'news',
            ];
        } catch (\Exception $e) {
            Log::error('AI News Generation Failed: ' . $e->getMessage());
            $this->apiKey = null; 
            return $this->generateNewsArticle($notes);
        }
    }

    public function generateCategoryPresentation($categoryName)
    {
        $prompt = "Tu es le Directeur Marketing de Kilatech. 
        OBJECTIF : Rédiger un texte de PRÉSENTATION DE GAMME pour la catégorie d'équipement : \"{$categoryName}\".
        
        TON : Professionnel, rassurant, expert et accueillant.
        ACCROCHE OBLIGATOIRE : Commencer par \"Kilatech vous présente sa gamme de {$categoryName}...\" ou une variante très proche.
        CONTENU : Explique que Kilatech sélectionne les meilleurs dispositifs pour cette spécialité, mentionne la qualité, le SAV et l'accompagnement.
        
        Format : JSON avec seo_title, seo_description, content (HTML avec <p>, <strong>).";

        if (empty($this->apiKey)) {
            $title = "Gamme $categoryName | Kilatech Maroc";
            return [
                'seo_title' => $title,
                'seo_description' => "Découvrez l'excellence de la gamme $categoryName chez Kilatech. Équipements de pointe pour les professionnels de santé au Maroc.",
                'content' => "<p><strong>Kilatech vous présente sa gamme de $categoryName.</strong></p><p>Parce que la précision est au cœur de votre métier, nous avons sélectionné pour vous des dispositifs médicaux alliant innovation technologique et fiabilité absolue. Notre expertise nous permet de vous accompagner dans le choix et l'installation de votre plateau technique partout au Maroc.</p>",
            ];
        }

        return $this->callAi($prompt);
    }

    public function diagnoseSocial($product)
    {
        $checks = [];
        $score = 100;

        $li = $product->social_linkedin_post ?? '';
        if (empty($li)) {
            $checks[] = ['id' => 'li_missing', 'status' => 'error', 'label' => 'LinkedIn manquant', 'advice' => 'Le post LinkedIn est crucial pour le B2B.'];
            $score -= 30;
        } else {
            if (mb_strlen($li) < 100) {
                $checks[] = ['id' => 'li_short', 'status' => 'warning', 'label' => 'LinkedIn trop court', 'advice' => 'Développez l\'aspect technique pour plus de crédibilité.'];
                $score -= 10;
            }
        }

        $fb = $product->social_facebook_post ?? '';
        if (empty($fb)) {
            $checks[] = ['id' => 'fb_missing', 'status' => 'error', 'label' => 'Facebook manquant', 'advice' => 'FB est idéal pour la proximité client au Maroc.'];
            $score -= 30;
        } else {
            if (!preg_match('/[\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}]/u', $fb)) {
                $checks[] = ['id' => 'fb_no_emojis', 'status' => 'warning', 'label' => 'FB manque d\'emojis', 'advice' => 'Utilisez des emojis pour un ton plus chaleureux.'];
                $score -= 10;
            }
        }

        $ig = $product->social_instagram_post ?? '';
        if (empty($ig)) {
            $checks[] = ['id' => 'ig_missing', 'status' => 'error', 'label' => 'Instagram manquant', 'advice' => 'Instagram booste l\'image de marque visuelle.'];
            $score -= 30;
        }

        return [
            'score' => max(0, $score),
            'health' => $score > 80 ? 'Excellent' : ($score > 50 ? 'Améliorable' : 'Critique'),
            'checks' => $checks
        ];
    }

    public function diagnoseSeo($product)
    {
        $checks = [];
        $score = 100;

        $title = $product->meta_title ?? '';
        $titleLen = mb_strlen($title);
        if ($titleLen === 0) {
            $checks[] = ['id' => 'title_missing', 'status' => 'error', 'label' => 'Titre Meta manquant', 'advice' => 'Ajoutez un titre pour le référencement Google.'];
            $score -= 30;
        }

        $desc = $product->meta_description ?? '';
        $descLen = mb_strlen($desc);
        if ($descLen === 0) {
            $checks[] = ['id' => 'desc_missing', 'status' => 'error', 'label' => 'Description manquante', 'advice' => 'Indispensable pour encourager le clic sur Google-Maroc.'];
            $score -= 30;
        }

        return [
            'score' => max(0, $score),
            'health' => $score > 80 ? 'Excellent' : ($score > 50 ? 'Améliorable' : 'Critique'),
            'checks' => $checks
        ];
    }

    protected function getMockData($product)
    {
        return [
            'slug' => Str::slug($product->name),
            'meta_title' => $product->name . " | Kilatech - Expert Médical Maroc",
            'meta_description' => "Découvrez le " . $product->name . " chez Kilatech. Performance, fiabilité et installation garantie pour votre clinique à Casablanca, Rabat et partout au Maroc.",
            'meta_keywords' => "matériel médical, Maroc, " . $product->category . ", " . $product->name . ", maintenance biomédicale",
            'social_linkedin_post' => "🏥 [Innovation Médicale] \n\nNous sommes fiers de vous présenter le nouveau " . $product->name . " désormais disponible au catalogue Kilatech.\n\n#Kilatech #MedicalDevice #SanteMaroc",
            'social_facebook_post' => "Nouveau chez Kilatech ! ✨\n\nLe " . $product->name . " arrive dans votre établissement. #Sante #Maroc",
            'social_instagram_post' => "Le futur de la santé arrive chez vous... 🚀 #BioMed #Kilatech",
            'local_geo_context' => "Disponible à Casablanca, Tanger, Marrakech et Agadir.",
        ];
    }
}
