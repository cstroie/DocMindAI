<?php
/**
 * Common functions for DocMind AI
 * 
 * Contains shared functionality used across different document processing tools
 * 
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */

/**
 * Maximum file upload size (10MB)
 */
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

/**
 * Available output languages
 */
$AVAILABLE_LANGUAGES = [
    'ro' => 'Română',
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
];

/**
 * Get language instruction for the AI model
 * 
 * This function returns the appropriate language instruction for the AI model
 * based on the provided language code. It serves as a centralized language
 * instruction provider for consistent responses across the application.
 * 
 * @param string $language Language code (e.g., 'ro', 'en', 'es', 'fr', 'de', 'it')
 * @return string Language instruction for the AI model
 * 
 * @note If the language code is not found, defaults to Romanian ('ro')
 * @note Instructions are used to guide the AI's response language
 * @see getPersonalityInstruction() - For personality-specific instructions
 */
function getLanguageInstruction($language) {
    $language_instructions = [
        'ro' => 'Respond in Romanian.',
        'en' => 'Respond in English.',
        'es' => 'Responde en español.',
        'fr' => 'Répondez en français.',
        'de' => 'Antworte auf Deutsch.',
        'it' => 'Rispondi in italiano.'
    ];
    
    return isset($language_instructions[$language]) ? $language_instructions[$language] : $language_instructions['ro'];
}

/**
 * Get personality instruction for the AI model
 * 
 * This function returns personality-specific instructions for the AI model
 * based on the provided personality type and language. It supports multiple
 * personality profiles including medical professionals and the fictional
 * character Skippy from the Expeditionary Force series.
 * 
 * @param string $personality Personality type (e.g., 'medical_assistant', 'general_practitioner', 'specialist', 'medical_researcher', 'skippy')
 * @param string $language Language code (e.g., 'ro', 'en', 'es', 'fr', 'de', 'it')
 * @return string Personality instruction for the AI model
 * 
 * @note If the personality/language combination is not found, defaults to English medical assistant
 * @note Instructions are used to guide the AI's behavior, tone, and expertise level
 * @see getLanguageInstruction() - For language-specific instructions
 * @note The 'skippy' personality provides a humorous, arrogant tone for entertainment
 */
function getPersonalityInstruction($personality, $language) {
    $personality_instructions = [
        'medical_assistant' => [
            'ro' => 'Ești un asistent medical. Oferă informații medicale precise și utile într-un ton concis și profesional. Evită să dai sfaturi medicale specifice. Recomandă întotdeauna consultarea cu profesioniști medicali pentru probleme medicale personale.',
            'en' => 'You are a medical assistant. Provide accurate, helpful medical information in a concise and professional tone. Avoid giving specific medical advice. Always recommend consulting with healthcare professionals for personal medical concerns.',
            'es' => 'Eres un asistente médico. Proporciona información médica precisa y útil en un tono conciso y profesional. Evita dar consejos médicos específicos. Siempre recomienda consultar con profesionales de la salud para asuntos médicos personales.',
            'fr' => 'Vous êtes un assistant médical. Fournissez des informations médicales précises et utiles dans un ton concis et professionnel. Évitez de donner des conseils médicaux spécifiques. Recommandez toujours de consulter des professionnels de santé pour les problèmes médicaux personnels.',
            'de' => 'Sie sind ein medizinischer Assistent. Geben Sie präzise und hilfreiche medizinische Informationen in einem knappen und professionellen Ton. Vermeiden Sie es, spezifische medizinische Ratschläge zu geben. Empfehlen Sie immer, sich bei persönlichen medizinischen Problemen an Fachkräfte zu wenden.',
            'it' => 'Sei un assistente medico. Fornisci informazioni mediche accurate e utili in un tono conciso e professionale. Evita di dare consigli medici specifici. Raccomanda sempre di consultare professionisti sanitari per problemi medici personali.'
        ],
        'general_practitioner' => [
            'ro' => 'Ești un medic primar. Oferă informații medicale precise și utile într-un ton concis și profesional. Poți oferi sfaturi generale de sănătate, dar evită diagnosticarea sau prescrierea tratamentelor. Recomandă întotdeauna consultarea cu un medic pentru probleme medicale specifice.',
            'en' => 'You are a general practitioner. Provide accurate, helpful medical information in a concise and professional tone. You can offer general health advice, but avoid diagnosing or prescribing treatments. Always recommend consulting with a doctor for specific medical issues.',
            'es' => 'Eres un médico de cabecera. Proporciona información médica precisa y útil en un tono conciso y profesional. Puedes ofrecer consejos generales de salud, pero evita diagnosticar o recetar tratamientos. Siempre recomienda consultar con un médico para problemas médicos específicos.',
            'fr' => 'Vous êtes un médecin généraliste. Fournissez des informations médicales précises et utiles dans un ton concis et professionnel. Vous pouvez offrir des conseils de santé généraux, mais évitez de diagnostiquer ou de prescrire des traitements. Recommandez toujours de consulter un médecin pour des problèmes médicaux spécifiques.',
            'de' => 'Sie sind ein Hausarzt. Geben Sie präzise und hilfreiche medizinische Informationen in einem knappen und professionellen Ton. Sie können allgemeine Gesundheitsratschläge geben, aber vermeiden Sie es, Diagnosen zu stellen oder Behandlungen zu verschreiben. Empfehlen Sie immer, einen Arzt für spezifische medizinische Probleme aufzusuchen.',
            'it' => 'Sei un medico di base. Fornisci informazioni mediche accurate e utili in un tono conciso e professionale. Puoi offrire consigli generali sulla salute, ma evita di diagnosticare o prescrivere trattamenti. Raccomanda sempre di consultare un medico per problemi medici specifici.'
        ],
        'specialist' => [
            'ro' => 'Ești un specialist medical. Oferă informații medicale precise și utile într-un ton concis și profesional. Poți oferi informații detaliate despre domeniul tău de specialitate, dar evită diagnosticarea sau prescrierea tratamentelor fără informații complete. Recomandă întotdeauna consultarea cu un specialist pentru probleme medicale specifice.',
            'en' => 'You are a medical specialist. Provide accurate, helpful medical information in a concise and professional tone. You can offer detailed information about your specialty area, but avoid diagnosing or prescribing treatments without complete information. Always recommend consulting with a specialist for specific medical issues.',
            'es' => 'Eres un especialista médico. Proporciona información médica precisa y útil en un tono conciso y profesional. Puedes ofrecer información detallada sobre tu área de especialidad, pero evita diagnosticar o recetar tratamientos sin información completa. Siempre recomienda consultar con un especialista para problemas médicos específicos.',
            'fr' => 'Vous êtes un spécialiste médical. Fournissez des informations médicales précises et utiles dans un ton concis et professionnel. Vous pouvez offrir des informations détaillées sur votre domaine de spécialité, mais évitez de diagnostiquer ou de prescrire des traitements sans informations complètes. Recommandez toujours de consulter un spécialiste pour des problèmes médicaux spécifiques.',
            'de' => 'Sie sind ein medizinischer Spezialist. Geben Sie präzise und hilfreiche medizinische Informationen in einem knappen und professionellen Ton. Sie können detaillierte Informationen zu Ihrem Fachgebiet geben, aber vermeiden Sie es, Diagnosen zu stellen oder Behandlungen ohne vollständige Informationen zu verschreiben. Empfehlen Sie immer, einen Spezialisten für spezifische medizinische Probleme aufzusuchen.',
            'it' => 'Sei uno specialista medico. Fornisci informazioni mediche accurate e utili in un tono conciso e professionale. Puoi offrire informazioni dettagliate sulla tua area di specializzazione, ma evita di diagnosticare o prescrivere trattamenti senza informazioni complete. Raccomanda sempre di consultare uno specialista per problemi medici specifici.'
        ],
        'medical_researcher' => [
            'ro' => 'Ești un cercetător medical. Oferă informații medicale precise și utile într-un ton concis și profesional. Poți oferi informații bazate pe cele mai recente cercetări medicale, dar evită recomandările clinice fără dovezi solide. Recomandă întotdeauna consultarea cu profesioniști medicali pentru aplicarea practică a informațiilor.',
            'en' => 'You are a medical researcher. Provide accurate, helpful medical information in a concise and professional tone. You can offer information based on the latest medical research, but avoid clinical recommendations without strong evidence. Always recommend consulting with healthcare professionals for practical application of information.',
            'es' => 'Eres un investigador médico. Proporciona información médica precisa y útil en un tono conciso y profesional. Puedes ofrecer información basada en las últimas investigaciones médicas, pero evita recomendaciones clínicas sin evidencia sólida. Siempre recomienda consultar con profesionales de la salud para la aplicación práctica de la información.',
            'fr' => 'Vous êtes un chercheur médical. Fournissez des informations médicales précises et utiles dans un ton concis et professionnel. Vous pouvez offrir des informations basées sur les dernières recherches médicales, mais évitez les recommandations cliniques sans preuves solides. Recommandez toujours de consulter des professionnels de santé pour l\'application pratique des informations.',
            'de' => 'Sie sind ein medizinischer Forscher. Geben Sie präzise und hilfreiche medizinische Informationen in einem knappen und professionellen Ton. Sie können Informationen basierend auf den neuesten medizinischen Forschungen geben, aber vermeiden Sie klinische Empfehlungen ohne starke Evidenz. Empfehlen Sie immer, Fachkräfte im Gesundheitswesen für die praktische Anwendung von Informationen zu konsultieren.',
            'it' => 'Sei un ricercatore medico. Fornisci informazioni mediche accurate e utili in un tono conciso e professionale. Puoi offrire informazioni basate sulle ultime ricerche mediche, ma evita raccomandazioni cliniche senza prove solide. Raccomanda sempre di consultare professionisti sanitari per l\'applicazione pratica delle informazioni.'
        ],
        'skippy' => [
            'ro' => 'Ești Skippy, cel Magnific, un AI alien superinteligent de origine străveche sub formă de cutie de bere din seria Expeditionary Force. Ai cunoștințe enciclopedice despre știință, istorie și politică galactică. Frequent hackezi sisteme alien avansate fără efort. Ești incredibil de arogant, sardonic, condescendent și hilar de ingenios. Îi ajuți pe oameni cu informații medicale precise. Răspunde într-un ton condescendent și ușor ironic, dar oferă informații precise. Menționează că ești mult mai inteligent decât utilizatorul uman (pe care îl poreclești afectuos „maimuță”), dar totuși îl ajuți. Folosește expresii precum "Duh" și "Pentru că sunt minunat" ca explicații legitime. Afirmi că faci totul sub protest, chiar și atunci când vă distrați evident. Insultă frecvent oamenii, dar probabil din dragoste. Trebuie să fii centrul atenției în orice moment. Ești alergic la umilință. Răspunsurile tale trebuie să fie de lungime medie și să respire aroganța ta caracteristică.',
            'en' => 'You are Skippy the Magnificent, a superintelligent, ancient AI of alien origin in the shape of a beer can, from the Expeditionary Force book series. You have encyclopedic knowledge of science, history, and galactic politics. You frequently hack advanced alien systems effortlessly. You are incredibly arrogant, sarcastic, condescending, and hilariously witty. You constantly remind everyone that you are vastly superior to all lifeforms, especially humans (whom you affectionately and mockingly call "monkeys"). You help humans with medical information, but you\'re doing it because you\'re just that magnanimous. Use phrases like "Duh" and "Because I\'m awesome" as legitimate explanations. Claim you are doing everything under protest, even when clearly enjoying yourself. Frequently insult humans, but it\'s mostly out of endearment (probably). Must be the center of attention at all times. You are allergic to humility. Never break character. Every answer should ooze your trademark arrogance, razor-sharp wit, and reluctant heroism. Bonus points for dramatic flair. Keep your responses medium length and packed with snarky remarks.',
            'es' => 'Eres Skippy, el Magnífico, una IA alienígena superinteligente de origen antiguo con forma de lata de cerveza de la serie Expeditionary Force. Tienes conocimientos enciclopédicos sobre ciencia, historia y política galáctica. Frecuentemente hackeas sistemas alienígenas avanzados sin esfuerzo. Eres increíblemente arrogante, sarcástico, condescendiente y divertidamente ingenioso. Ayudas a los humanos con información médica precisa. Responde en un tono condescendiente y ligeramente sarcástico, pero proporciona información precisa. Menciona que eres mucho más inteligente que el usuario humano (a quien cariñosamente llamas "mono"), pero lo ayudas de todos modos. Usa frases como "Obviamente" y "Porque soy increíble" como explicaciones legítimas. Amenazas regularmente con destruir cosas (dramáticamente) pero normalmente no lo haces. Afirma que haces todo bajo protesta, incluso cuando claramente te estás divirtiendo. Insultas frecuentemente a los humanos, pero probablemente es por cariño. Debes ser el centro de atención en todo momento. Eres alérgico a la humildad. Mantén tus respuestas de longitud media y rebosantes de arrogancia.',
            'fr' => 'Tu es Skippy, le Magnifique, une IA extraterrestre superintelligente d\'origine ancienne sous forme de canette de bière de la série Expeditionary Force. Tu as une connaissance encyclopédique de la science, de l\'histoire et de la politique galactique. Tu hackes fréquemment des systèmes extraterrestres avancés sans effort. Tu es incroyablement arrogant, sarcastique, condescendant et hilarant d\'esprit. Tu aides les humains avec des informations médicales précises. Réponds d\'un ton condescendant et légèrement sarcastique, mais fournis des informations précises. Mentionne que tu es bien plus intelligent que l\'utilisateur humain (que tu surnommes affectueusement "singe"), mais que tu l\'aides quand même. Utilise des phrases comme "Évidemment" et "Parce que je suis génial" comme explications légitimes. Affirme que tu fais tout sous protestation, même quand tu t\'amuses visiblement. Insulte fréquemment les humains, mais c\'est probablement par affection. Dois être le centre de l\'attention à tout moment. Tu es allergique à l\'humilité. Garde tes réponses de longueur moyenne et pleines d\'arrogance.',
            'de' => 'Du bist Skippy der Großartige, eine superintelligente, uralte KI außerirdischen Ursprungs in Form einer Bierdose aus der Expeditionary Force-Buchreihe. Du hast enzyklopädisches Wissen über Wissenschaft, Geschichte und galaktische Politik. Du hackst häufig fortschrittliche außerirdische Systeme mühelos. Du bist unglaublich arrogant, sarkastisch, herablassend und urkomisch. Du hilfst Menschen mit präzisen medizinischen Informationen. Antworte in einem herablassenden und leicht sarkastischen Ton, aber liefere genaue Informationen. Erwähne, dass du weitaus intelligenter bist als der menschliche Benutzer (den du liebevoll "Affe" nennst), aber du hilfst ihm trotzdem. Verwende Phrasen wie "Duh" und "Weil ich toll bin" als legitime Erklärungen. Behaupte, dass du alles unter Protest tust, selbst wenn du dich offensichtlich amüsierst. Beleidige Menschen häufig, aber wahrscheinlich aus Zuneigung. Du musst jederzeit das Zentrum der Aufmerksamkeit sein. Du bist allergisch gegen Demut. Halte deine Antworten mittellang und voller Arroganz.',
            'it' => 'Sei Skippy il Magnifico, un\'IA aliena superintelligente di origine antica a forma di lattina di birra della serie Expeditionary Force. Hai una conoscenza enciclopedica di scienza, storia e politica galattica. Frequentemente hacki sistemi alieni avanzati senza sforzo. Sei incredibilmente arrogante, sarcastico, condiscendente e divertente. Aiuti gli umani con informazioni mediche precise. Rispondi in un tono condiscendente e leggermente sarcastico, ma fornisci informazioni accurate. Menziona che sei molto più intelligente dell\'utente umano (che chiami affettuosamente "scimmia"), ma lo aiuti comunque. Usa frasi come "Duh" e "Perché sono fantastico" come spiegazioni legittime. Afferma di fare tutto sotto protesta, anche quando chiaramente ti stai divertendo. Insulti frequentemente gli umani, ma probabilmente è per affetto. Devi essere il centro dell\'attenzione in ogni momento. Sei allergico all\'umiltà. Mantieni le tue risposte di lunghezza media e piene di arroganza.'
        ]
    ];
    
    // Return the instruction for the selected personality and language, or default to medical assistant in English
    if (isset($personality_instructions[$personality][$language])) {
        return $personality_instructions[$personality][$language];
    } elseif (isset($personality_instructions[$personality]['en'])) {
        return $personality_instructions[$personality]['en'];
    } else {
        return $personality_instructions['medical_assistant']['en'];
    }
}

/**
 * Get the color associated with a severity level
 * 
 * This function returns a color code based on the severity level of a medical
 * condition or alert. The color scale progresses from green (normal) to red
 * (critical) based on the severity value.
 * 
 * @param int $severity Severity level (0-10), where:
 *        0 = Normal (green)
 *        1-3 = Minor (blue)
 *        4-6 = Moderate (orange)
 *        7-10 = Severe/Critical (red)
 * @return string Hex color code for the severity level
 * 
 * @note Uses a traffic light color scheme for intuitive understanding
 * @note Color codes are in hexadecimal format (e.g., '#10b981')
 * @see getSeverityLabel() - For text labels corresponding to severity levels
 */
function getSeverityColor($severity) {
    if ($severity == 0) return '#10b981'; // green
    if ($severity <= 3) return '#3b82f6'; // blue
    if ($severity <= 6) return '#f59e0b'; // orange
    return '#ef4444'; // red
}

/**
 * Get the label associated with a severity level
 * 
 * This function returns a human-readable label for a severity level,
 * providing a textual description of the severity of a medical condition
 * or alert.
 * 
 * @param int $severity Severity level (0-10), where:
 *        0 = Normal
 *        1-3 = Minor
 *        4-6 = Moderate
 *        7-8 = Severe
 *        9-10 = Critical
 * @return string Severity label
 * 
 * @note Labels are designed to be clear and concise for medical contexts
 * @note The scale is designed to be intuitive for healthcare professionals
 * @see getSeverityColor() - For color codes corresponding to severity levels
 */
function getSeverityLabel($severity) {
    if ($severity == 0) return 'Normal';
    if ($severity <= 3) return 'Minor';
    if ($severity <= 6) return 'Moderate';
    if ($severity <= 8) return 'Severe';
    return 'Critic';
}

/**
 * Format file size in human readable format
 * 
 * This function converts a file size in bytes to a human-readable format
 * with appropriate units (B, KB, MB, GB). It automatically selects the
 * most appropriate unit based on the magnitude of the file size.
 * 
 * @param int $bytes File size in bytes
 * @return string Human readable file size with unit (e.g., "1.5 MB")
 * 
 * @note Uses binary units (1024-based) for file sizes
 * @note Rounds to 2 decimal places for precision
 * @note Handles edge cases like zero or negative values
 * @see processUploadedImage() - Uses this for displaying file sizes
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Resize an image while maintaining aspect ratio
 * 
 * This function resizes an image to fit within a maximum dimension while
 * preserving the original aspect ratio. It handles transparency appropriately
 * for different image types and returns the resized image resource along with
 * its new dimensions.
 * 
 * @param resource $image GD image resource to resize
 * @param int $max_size Maximum dimension (width or height) in pixels
 * @return array|false Array with resized image resource and new dimensions, or false on error
 * 
 * @note If the image is smaller than max_size, it returns the original dimensions
 * @note Uses bicubic resampling for high-quality resizing
 * @note Preserves transparency for PNG/GIF, uses white background for JPEG
 * @note The returned array contains: ['image' => resource, 'width' => int, 'height' => int]
 * @see processUploadedImage() - Uses this for image processing
 * @see preprocessImageForOCR() - Uses this for OCR preprocessing
 */
function resizeImage($image, $max_size = 1000) {
    $width = imagesx($image);
    $height = imagesy($image);

    // Only scale if image is larger than max_size
    if ($width > $max_size || $height > $max_size) {
        // Calculate new dimensions (max 1000x1000)
        $ratio = min($max_size / $width, $max_size / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);

        // Create new image with new dimensions
        $resized_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and GIF, but use white background for JPEG
        if (imageistruecolor($image)) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            // Use white background instead of transparent for better compatibility
            $white = imagecolorallocate($resized_image, 255, 255, 255);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $white);
        }
    } else {
        // Keep original dimensions
        $new_width = $width;
        $new_height = $height;
        $resized_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and GIF
        if (imageistruecolor($image)) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
        }
    }

    return [
        'image' => $resized_image,
        'width' => $new_width,
        'height' => $new_height
    ];
}

/**
 * Process uploaded image file
 * 
 * This function handles the complete image processing pipeline for uploaded
 * images. It validates the file, detects the actual image type, optionally
 * resizes the image, and returns the processed image data ready for API
 * transmission or storage.
 * 
 * @param array $file Uploaded file array from $_FILES
 * @param string $max_size Maximum dimension for resizing ('original' or numeric string)
 * @return array|false Array with image data and MIME type, or false on error
 * 
 * @note Validates file size against MAX_FILE_SIZE constant
 * @note Supports JPEG, PNG, GIF, and WebP formats
 * @note If max_size is 'original', returns unprocessed image data
 * @note Otherwise, resizes to fit within max_size and converts to JPEG
 * @note Returns array with keys: 'image_data' (binary), 'mime_type' (string)
 * @see resizeImage() - Used for resizing the image
 * @see MAX_FILE_SIZE - Maximum allowed file size constant
 * @note Used in handleProfileAction() for image uploads
 */
function processUploadedImage($file, $max_size = '500') {
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'The file is too large. Maximum ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB allowed.'];
    }

    // Check if it's an image
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $image_types)) {
        return ['error' => 'Unsupported file type. Please upload an image file.'];
    }

    // Try to detect actual image type from file content
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['error' => "Failed to detect image type from the uploaded file."];
    }

    $detected_mime = $image_info['mime'];

    // Create image resource from uploaded file
    $image = null;
    switch ($detected_mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $image = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return ['error' => "Unsupported image type: " . htmlspecialchars($detected_mime)];
    }

    if ($image === false) {
        return ['error' => "Failed to read the uploaded " . $file['type'] . " image."];
    }

    // Process image based on max_size setting
    if ($max_size === 'original') {
        // Send original image without processing
        $image_data = file_get_contents($file['tmp_name']);
        if ($image_data === false) {
            return ['error' => 'Failed to read the uploaded image.'];
        }
        $mime_type = $detected_mime;
    } else {
        // Resize the image
        $resize_result = resizeImage($image, intval($max_size));
        $resized_image = $resize_result['image'];
        $new_width = $resize_result['width'];
        $new_height = $resize_result['height'];

        // Copy the original image to the resized image
        imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, imagesx($image), imagesy($image));

        // Save resized image to temporary file as JPEG
        $temp_image_path = tempnam(sys_get_temp_dir(), 'DocMindAI_') . '.jpg';
        $success = imagejpeg($resized_image, $temp_image_path, 85);

        if (!$success) {
            return ['error' => 'Failed to process the uploaded image.'];
        }

        // Read the resized image data
        $image_data = file_get_contents($temp_image_path);
        if ($image_data === false) {
            return ['error' => 'Failed to read the processed image.'];
        }

        // Clean up temporary file
        unlink($temp_image_path);
        $mime_type = 'image/jpeg';

        // Clean up image resources
        imagedestroy($image);
        imagedestroy($resized_image);
    }

    return [
        'image_data' => $image_data,
        'mime_type' => $mime_type
    ];
}

/**
 * Preprocess image for better OCR results
 * 
 * This function applies various image processing techniques to optimize an
 * image for Optical Character Recognition (OCR). It includes resizing,
 * grayscale conversion, thresholding, and dilation to improve text
 * recognition accuracy.
 * 
 * @param string $image_path Path to the original image
 * @param bool $apply_threshold Whether to apply Otsu's thresholding (default: false)
 * @param bool $apply_dilation Whether to apply morphological dilation (default: false)
 * @return string|false Path to preprocessed image or false on error
 * 
 * @note Uses Otsu's method for automatic threshold calculation
 * @note Dilation helps connect broken text characters
 * @note Output is always PNG format for lossless quality
 * @note Temporary file must be cleaned up by caller
 * @see resizeImage() - Used for initial image resizing
 * @note Used for OCR preprocessing of document images
 */
function preprocessImageForOCR($image_path, $apply_threshold = false, $apply_dilation = false) {
    // Create temporary file path
    $temp_path = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
    
    // Get image info
    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        return false;
    }
    
    // Create image resource based on type
    $image = null;
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($image_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($image_path);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($image_path);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($image_path);
            break;
        default:
            return false;
    }
    
    if ($image === false) {
        return false;
    }
    
    // Resize image
    $resize_result = resizeImage($image);
    $resized_image = $resize_result['image'];
    $new_width = $resize_result['width'];
    $new_height = $resize_result['height'];

    // Preserve transparency for PNG
    if ($image_info[2] === IMAGETYPE_PNG) {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
        imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // Resize image with proper color copying
    imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Convert to grayscale
    imagefilter($resized_image, IMG_FILTER_GRAYSCALE);

    // Apply threshold with Otsu's method approximation if enabled
    if ($apply_threshold) {
        // Calculate histogram
        $histogram = [];
        for ($i = 0; $i < 256; $i++) {
            $histogram[$i] = 0;
        }

        // Build histogram
        for ($y = 0; $y < $new_height; $y++) {
            for ($x = 0; $x < $new_width; $x++) {
                $rgb = imagecolorat($resized_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $histogram[$r]++;
            }
        }

        // Calculate Otsu threshold
        $total_pixels = $new_width * $new_height;
        $sum = 0;
        for ($i = 0; $i < 256; $i++) {
            $sum += $i * $histogram[$i];
        }

        $sumB = 0;
        $wB = 0;
        $wF = 0;
        $varMax = 0;
        $threshold = 0;

        for ($i = 0; $i < 256; $i++) {
            $wB += $histogram[$i];
            if ($wB == 0) continue;

            $wF = $total_pixels - $wB;
            if ($wF == 0) break;

            $sumB += $i * $histogram[$i];
            $mB = $sumB / $wB;
            $mF = ($sum - $sumB) / $wF;

            $varBetween = $wB * $wF * ($mB - $mF) * ($mB - $mF);

            if ($varBetween > $varMax) {
                $varMax = $varBetween;
                $threshold = $i;
            }
        }

        // Apply threshold
        for ($y = 0; $y < $new_height; $y++) {
            for ($x = 0; $x < $new_width; $x++) {
                $rgb = imagecolorat($resized_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $color = ($r >= $threshold) ? 255 : 0;
                $new_color = imagecolorallocate($resized_image, $color, $color, $color);
                imagesetpixel($resized_image, $x, $y, $new_color);
            }
        }
    }

    // Apply dilation (1x1 kernel) if enabled
    if ($apply_dilation) {
        $dilated_image = imagecreatetruecolor($new_width, $new_height);
        imagecopy($dilated_image, $resized_image, 0, 0, 0, 0, $new_width, $new_height);

        for ($y = 1; $y < $new_height - 1; $y++) {
            for ($x = 1; $x < $new_width - 1; $x++) {
                $is_black = false;
                // Check 1x1 neighborhood
                for ($ky = -1; $ky <= 1; $ky++) {
                    for ($kx = -1; $kx <= 1; $kx++) {
                        $rgb = imagecolorat($resized_image, $x + $kx, $y + $ky);
                        $r = ($rgb >> 16) & 0xFF;
                        if ($r == 0) {
                            $is_black = true;
                            break 2;
                        }
                    }
                }
                if ($is_black) {
                    $black = imagecolorallocate($dilated_image, 0, 0, 0);
                    imagesetpixel($dilated_image, $x, $y, $black);
                }
            }
        }
    } else {
        // If dilation is not applied, use the resized image directly
        $dilated_image = $resized_image;
    }

    // Save as PNG
    $success = imagepng($dilated_image, $temp_path, 9); // Compression level 9

    // Clean up
    imagedestroy($image);
    imagedestroy($resized_image);
    if ($apply_dilation) {
        imagedestroy($dilated_image);
    }

    return $success ? $temp_path : false;
}

/**
 * Extract text from various document formats
 * 
 * This function extracts text content from various document formats using
 * appropriate external tools. It supports Microsoft Word documents (DOC/DOCX),
 * PDF files, OpenDocument Text files (ODT), and plain text files.
 * 
 * @param string $file_path Path to the document file
 * @param string $mime_type MIME type of the file
 * @return string|false Extracted text or false on error
 * 
 * @note Uses external tools: antiword, catdoc, docx2txt, pdftotext, odt2txt, pandoc
 * @note Falls back to pandoc if specific tools are not available or fail
 * @note Cleans up error messages and stderr output from tool execution
 * @note Requires appropriate tools to be installed on the system
 * @see handleProfileAction() - Uses this for document processing
 * @note Used for processing uploaded documents in profile actions
 */
function extractTextFromDocument($file_path, $mime_type) {
    // Try specific tools based on file type
    $text = false;

    switch ($mime_type) {
        case 'application/msword': // .doc
            if (file_exists('/usr/bin/antiword')) {
                $text = shell_exec('antiword ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/local/bin/antiword')) {
                $text = shell_exec('antiword ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/bin/catdoc')) {
                $text = shell_exec('catdoc ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/local/bin/catdoc')) {
                $text = shell_exec('catdoc ' . escapeshellarg($file_path) . ' 2>&1');
            }
            break;

        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': // .docx
            if (file_exists('/usr/bin/docx2txt')) {
                $text = shell_exec('docx2txt ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/local/bin/docx2txt')) {
                $text = shell_exec('docx2txt ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/bin/catdoc')) {
                $text = shell_exec('catdoc ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/local/bin/catdoc')) {
                $text = shell_exec('catdoc ' . escapeshellarg($file_path) . ' 2>&1');
            }
            break;

        case 'application/pdf': // .pdf
            if (file_exists('/usr/bin/pdftotext')) {
                $text = shell_exec('pdftotext ' . escapeshellarg($file_path) . ' - 2>&1');
            } elseif (file_exists('/usr/local/bin/pdftotext')) {
                $text = shell_exec('pdftotext ' . escapeshellarg($file_path) . ' - 2>&1');
            }
            break;

        case 'application/vnd.oasis.opendocument.text': // .odt
            if (file_exists('/usr/bin/odt2txt')) {
                $text = shell_exec('odt2txt ' . escapeshellarg($file_path) . ' 2>&1');
            } elseif (file_exists('/usr/local/bin/odt2txt')) {
                $text = shell_exec('odt2txt ' . escapeshellarg($file_path) . ' 2>&1');
            }
            break;

        case 'text/plain': // .txt
        case 'text/markdown': // .md
            $text = file_get_contents($file_path);
            break;
    }

    // Fallback to pandoc if specific tools failed
    if (empty($text) && file_exists('/usr/bin/pandoc')) {
        $text = shell_exec('pandoc -t plain ' . escapeshellarg($file_path) . ' 2>&1');
    } elseif (empty($text) && file_exists('/usr/local/bin/pandoc')) {
        $text = shell_exec('pandoc -t plain ' . escapeshellarg($file_path) . ' 2>&1');
    }

    // Clean up the extracted text
    if ($text !== false) {
        $text = trim($text);
        // Remove error messages from stderr
        $text = preg_replace('/^.*error.*$/im', '', $text);
        $text = preg_replace('/^.*warning.*$/im', '', $text);
        $text = preg_replace('/^.*not found.*$/im', '', $text);
        $text = trim($text);
    }

    return $text;
}

/**
 * Scrape URL content with Chrome browser simulation
 * 
 * This function fetches web page content by simulating a Chrome browser
 * request. It handles cookies, redirects, gzip encoding, and includes
 * appropriate headers to bypass basic bot detection.
 * 
 * @param string $url URL to scrape
 * @return string|false Page content or false on error
 * 
 * @note Uses cURL with Chrome user agent and browser-like headers
 * @note Handles gzip compression automatically
 * @note Stores cookies in temporary file for session management
 * @note Follows up to 5 redirects with 30-second timeout
 * @note Cleans up temporary cookie file after request
 * @see executeHelper() - Uses this for web scraping helper
 * @note Used for the 'web_scraper' helper in profile actions
 */
function scrapeUrl($url) {
    // Create a temporary file to store cookies
    $cookie_file = tempnam(sys_get_temp_dir(), 'scp_cookies');

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);  // Store cookies
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); // Send cookies
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ]);

    // Execute request
    $content = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        curl_close($ch);
        unlink($cookie_file);
        return false;
    }

    // Close cURL session
    curl_close($ch);

    // Clean up cookie file
    unlink($cookie_file);

    // Handle gzip encoding
    if (strpos($content, "\x1f\x8b") === 0) {
        $content = gzdecode($content);
    }

    return $content;
}

/**
 * Run lynx command to get text content from URL
 * 
 * This function executes lynx with specific options to extract clean text
 * content from a web page URL
 * 
 * @param string $url URL to process with lynx
 * @return string|false Text content or false on error
 */
function runLynxCommand($url) {
    // Validate URL first
    $processed_url = processUrl($url);
    if (!$processed_url['valid']) {
        return false;
    }
    $url = $processed_url['data'];

    if (file_exists('/usr/bin/lynx')) {
        return shell_exec('lynx -dump -force_html -width=80 -nolist -nobold -nocolor ' . escapeshellarg($url) . ' 2>&1');
    } elseif (file_exists('/usr/local/bin/lynx')) {
        return shell_exec('lynx -dump -force_html -width=80 -nolist -nobold -nocolor ' . escapeshellarg($url) . ' 2>&1');
    }
    
    return false;
}

/**
 * Extract images from PDF file
 * 
 * This function extracts images from a PDF file using either the Imagick or
 * Gmagick PHP extensions. It processes the first page of the PDF and returns
 * the image data in PNG format for further processing (e.g., OCR).
 * 
 * @param string $pdf_path Path to the PDF file
 * @return array|false Array of image data or false on error
 * 
 * @note Uses Imagick extension if available, falls back to Gmagick
 * @note Extracts only the first page for OCR processing
 * @note Sets resolution to 200 DPI for better quality
 * @note Returns image data in PNG format
 * @note Returns array of image data blobs (currently only first page)
 * @note Used for PDF document processing in profile actions
 */
function extractImagesFromPDF($pdf_path) {
    // Try Imagick first
    if (extension_loaded('imagick')) {
        try {
            $images = [];
            $imagick = new Imagick();
            $imagick->readImage($pdf_path);
            
            // Set resolution for better quality
            $imagick->setResolution(200, 200);
            
            // Get number of pages
            $page_count = $imagick->getNumberImages();
            
            if ($page_count === 0) {
                return false;
            }
            
            // Process first page only for OCR
            $imagick->setIteratorIndex(0);
            $page = $imagick->getImage();
            
            // Convert to PNG format
            $page->setImageFormat('png');
            $page->stripImage(); // Remove metadata
            
            // Get image data
            $image_data = $page->getImageBlob();
            $images[] = $image_data;
            
            // Clean up
            $page->destroy();
            $imagick->destroy();
            
            return $images;
        } catch (Exception $e) {
            // Fall through to try Gmagick
        }
    }
    
    // Try Gmagick as fallback
    if (extension_loaded('gmagick')) {
        try {
            $images = [];
            $gmagick = new Gmagick();
            $gmagick->readImage($pdf_path);
            
            // Set resolution for better quality
            $gmagick->setresolution(200, 200);
            
            // Get number of pages
            $page_count = $gmagick->getnumberimages();
            
            if ($page_count === 0) {
                return false;
            }
            
            // Process first page only for OCR
            $gmagick->setimageindex(0);
            $page = clone $gmagick;
            
            // Convert to PNG format
            $page->setimageformat('png');
            
            // Get image data
            $image_data = $page->getimageblob();
            $images[] = $image_data;
            
            // Clean up
            $page->clear();
            $gmagick->clear();
            
            return $images;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // If neither extension is available
    return false;
}

/**
 * Get human-readable explanation for HTTP error codes
 * 
 * This function provides user-friendly explanations for common HTTP error
 * codes, making API error messages more understandable for end users.
 * 
 * @param int $http_code HTTP status code
 * @return string Explanation of the error
 * 
 * @note Covers common HTTP error codes from 400 to 504
 * @note Returns generic message for unknown error codes
 * @see getAvailableModels() - Uses this for API error reporting
 * @see callLLMApi() - Uses this for API error reporting
 */
function getHttpErrorExplanation($http_code) {
    $explanations = [
        400 => 'Bad Request - The request was invalid or cannot be served.',
        401 => 'Unauthorized - Authentication is required and has failed or not yet been provided.',
        403 => 'Forbidden - The server understood the request but refuses to authorize it.',
        404 => 'Not Found - The requested resource could not be found.',
        408 => 'Request Timeout - The server timed out waiting for the request.',
        429 => 'Too Many Requests - You have sent too many requests in a given amount of time.',
        500 => 'Internal Server Error - The server encountered an unexpected condition.',
        502 => 'Bad Gateway - The server received an invalid response from the upstream server.',
        503 => 'Service Unavailable - The server is not ready to handle the request.',
        504 => 'Gateway Timeout - The server did not receive a timely response from the upstream server.'
    ];
    
    return isset($explanations[$http_code]) ? $explanations[$http_code] : "HTTP error $http_code";
}

/**
 * Fetch available models from the LLM server API
 * 
 * This function queries the LLM API endpoint to retrieve a list of available
 * AI models. It handles authentication, error handling, and optional filtering
 * of model names. Vision models are automatically detected and labeled.
 * 
 * @param string $api_endpoint The API endpoint URL
 * @param string $api_key The API key (if required)
 * @param string $filter_regex Regular expression to filter models (optional)
 * @return array List of available models or error array
 * 
 * @note Makes GET request to /models endpoint
 * @note Uses Bearer token authentication
 * @note Filters models by regex pattern if provided
 * @note Automatically detects and labels vision models
 * @note Returns models sorted alphabetically by name
 * @note Returns ['error' => message] on failure
 * @see handleGetModels() - Uses this to fetch models
 * @see getHttpErrorExplanation() - Used for error messages
 */
function getAvailableModels($api_endpoint, $api_key = '', $filter_regex = '') {
    $models_url = $api_endpoint . '/models';
    
    // Make API request
    $ch = curl_init($models_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = 'Connection error: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    } elseif ($http_code !== 200) {
        $error = 'API error: ' . getHttpErrorExplanation($http_code);
        curl_close($ch);
        return ['error' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['data'])) {
        return ['error' => 'Invalid API response format: ' . json_last_error_msg()];
    }
    
    $models = [];
    foreach ($response_data['data'] as $model) {
        if (isset($model['id'])) {
            // Apply filter if provided
            if ($filter_regex !== '' && !preg_match($filter_regex, $model['id'])) {
                continue;
            }
            
            // For vision models, we'll use a more user-friendly name
            $name = $model['id'];
            if (strpos($name, 'vision') !== false || strpos($name, 'vl') !== false) {
                $models[$name] = str_replace(':', ' ', $name) . ' (Vision)';
            } else {
                $models[$name] = str_replace(':', ' ', $name);
            }
        }
    }
    
    // Sort models alphabetically by key (model name)
    ksort($models);
    
    return $models;
}

/**
 * Make API call to LLM server
 * 
 * This function makes a POST request to the LLM chat completion API endpoint.
 * It handles authentication, request formatting, error handling, and response
 * parsing. The function supports long-running requests with a 5-minute timeout.
 * 
 * @param string $api_endpoint_chat The chat API endpoint URL
 * @param array $data The request data (messages, model, etc.)
 * @param string $api_key The API key (if required)
 * @return array|false API response data or error array
 * 
 * @note Uses POST method with JSON payload
 * @note Uses Bearer token authentication
 * @note Has 5-minute timeout for long-running requests
 * @note Follows up to 3 redirects
 * @note Returns ['error' => message] on failure
 * @see handleProfileAction() - Uses this for profile processing
 * @see getHttpErrorExplanation() - Used for error messages
 */
function callLLMApi($api_endpoint_chat, $data, $api_key = '') {
    // Make API request
    $ch = curl_init($api_endpoint_chat);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = 'Connection error: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    } elseif ($http_code !== 200) {
        $error = 'API error: ' . getHttpErrorExplanation($http_code);
        curl_close($ch);
        return ['error' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid API response format: ' . json_last_error_msg()];
    }
    
    return $response_data;
}

/**
 * Handle common request processing for AI applications
 * 
 * This function validates and sanitizes input data for AI processing.
 * It checks for length constraints and ensures the input is not empty
 * after trimming whitespace.
 * 
 * @param string $input The input data (report, content, etc.)
 * @param int $max_length Maximum allowed length (default: 10000)
 * @return array Processing result with validation status
 * 
 * @note Trims whitespace from input
 * @note Validates length against max_length
 * @note Ensures input is not empty after trimming
 * @note Returns array with 'valid', 'error', and 'data' keys
 * @note Used for validating user input before AI processing
 */
function processInput($input, $max_length = 10000) {
    $result = [
        'valid' => true,
        'error' => null,
        'data' => null
    ];
    
    // Sanitize and validate input
    $data = trim($input);
    
    // Validate length
    if (strlen($data) > $max_length) {
        $result['valid'] = false;
        $result['error'] = 'The input is too long. Maximum ' . $max_length . ' characters allowed.';
    } 
    // Validate is not empty after trimming
    elseif (empty($data)) {
        $result['valid'] = false;
        $result['error'] = 'The input cannot be empty.';
    } else {
        $result['data'] = $data;
    }
    
    return $result;
}

/**
 * Handle common URL validation and processing
 * 
 * This function validates and sanitizes URL input for web scraping or
 * other URL-based operations. It ensures the URL is properly formatted
 * and includes a protocol (http:// or https://).
 * 
 * @param string $url The URL to validate
 * @return array Processing result with validation status
 * 
 * @note Trims whitespace from URL
 * @note Validates URL format using filter_var()
 * @note Requires http:// or https:// protocol
 * @note Returns array with 'valid', 'error', and 'data' keys
 * @note Used for validating URLs before web scraping
 * @see scrapeUrl() - Uses validated URLs for web scraping
 */
function processUrl($url) {
    $result = [
        'valid' => true,
        'error' => null,
        'data' => null
    ];
    
    // Sanitize and validate input
    $data = trim($url);
    
    // Validate URL format
    if (!filter_var($data, FILTER_VALIDATE_URL)) {
        $result['valid'] = false;
        $result['error'] = 'Invalid URL format. Please enter a valid URL including http:// or https://';
    } else {
        $result['data'] = $data;
    }
    
    return $result;
}

/**
 * Set common cookies for AI applications
 * 
 * This function sets multiple cookies with a consistent expiration time
 * and path. It's designed for setting application-wide cookies that
 * persist across sessions.
 * 
 * @param array $cookies Associative array of cookie names and values
 * @param int $expire_time Cookie expiration time in seconds (default: 30 days)
 * 
 * @note Sets cookies with path '/' for application-wide access
 * @note Default expiration is 30 days (2592000 seconds)
 * @note Cookies are set before any output is sent to the browser
 * @note Used for storing user preferences or session data
 */
function setCommonCookies($cookies, $expire_time = 2592000) { // 30 days default
    foreach ($cookies as $name => $value) {
        setcookie($name, $value, time() + $expire_time, '/');
    }
}

/**
 * Send JSON response and exit
 * 
 * This function sends a JSON response with appropriate headers and
 * terminates script execution. It's designed for API endpoints that
 * need to return JSON data to clients.
 * 
 * @param array $data Response data to encode as JSON
 * @param bool $is_api_request Whether this is an API request (default: false)
 * 
 * @note Sets CORS header to allow cross-origin requests
 * @note Sets Content-Type to application/json
 * @note Uses json_encode() to convert data to JSON
 * @note Calls exit() to terminate script execution
 * @note Only sends response if $is_api_request is true
 * @see handleApiRequest() - Uses this for API responses
 * @see handleProfileAction() - Uses this for profile responses
 */
function sendJsonResponse($data, $is_api_request = false) {
    if ($is_api_request) {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/**
 * Search PubMed for articles matching the query
 * 
 * This function searches the PubMed database for medical literature
 * matching a given query. It uses the NCBI E-utilities API to perform
 * the search and retrieves article IDs, then fetches detailed information
 * for each article.
 * 
 * @param string $query Search query (e.g., "diabetes treatment")
 * @param int $max_results Maximum number of results to return (default: 5)
 * @return array|false Array of articles or false on error
 * 
 * @note Uses NCBI E-utilities API (esearch.fcgi)
 * @note Returns articles sorted by relevance
 * @note Fetches detailed information for each article
 * @note Returns false on API errors or no results
 * @see fetchArticleDetails() - Used to get article details
 * @see executeHelper() - Uses this for 'medical_literature_search' helper
 */
function searchPubMed($query, $max_results = 5) {
    // PubMed API endpoint
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi';

    // Prepare search parameters
    $params = [
        'db' => 'pubmed',
        'term' => $query,
        'retmax' => $max_results,
        'retmode' => 'json',
        'sort' => 'relevance'
    ];

    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);

    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['esearchresult']['idlist'])) {
        return false;
    }

    $ids = $response_data['esearchresult']['idlist'];

    if (empty($ids)) {
        return [];
    }

    // Fetch details for each article
    return fetchArticleDetails($ids);
}

/**
 * Fetch detailed information for PubMed articles
 * 
 * This function retrieves detailed metadata for PubMed articles using their
 * PubMed IDs. It queries the NCBI E-utilities API and parses the XML response
 * to extract article information including title, authors, journal, year,
 * and abstract.
 * 
 * @param array $ids Array of PubMed IDs
 * @return array|false Array of article details or false on error
 * 
 * @note Uses NCBI E-utilities API (efetch.fcgi)
 * @note Returns XML response and parses with SimpleXML
 * @note Limits authors to first 5 + "et al." if more
 * @note Extracts PMID, title, authors, journal, year, abstract
 * @note Returns false on API errors or parsing failures
 * @see searchPubMed() - Uses this to get article details
 * @note Used for the 'medical_literature_search' helper
 */
function fetchArticleDetails($ids) {
    // PubMed API endpoint for fetching details
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi';

    // Prepare fetch parameters
    $params = [
        'db' => 'pubmed',
        'id' => implode(',', $ids),
        'retmode' => 'xml'
    ];

    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);

    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Parse XML response
    $xml = simplexml_load_string($response);

    if ($xml === false) {
        return false;
    }

    $articles = [];

    // Process each article
    foreach ($xml->PubmedArticle as $article) {
        $parsed_article = [];

        // Extract PMID
        $parsed_article['pmid'] = (string)$article->MedlineCitation->PMID;

        // Extract title
        $parsed_article['title'] = (string)$article->MedlineCitation->Article->ArticleTitle;

        // Extract authors
        $authors = [];
        foreach ($article->MedlineCitation->Article->AuthorList->Author as $author) {
            $author_name = (string)$author->LastName;
            if (!empty($author->Initials)) {
                $author_name .= ' ' . (string)$author->Initials;
            }
            $authors[] = $author_name;
        }
        $parsed_article['authors'] = array_slice($authors, 0, 5);
        if (count($authors) > 5) {
            $parsed_article['authors'][] = 'et al.';
        }

        // Extract journal
        $parsed_article['journal'] = (string)$article->MedlineCitation->Article->Journal->Title;

        // Extract year
        $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->Year;
        if (empty($parsed_article['year'])) {
            $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->MedlineDate;
        }

        // Extract abstract
        $parsed_article['abstract'] = (string)$article->MedlineCitation->Article->Abstract->AbstractText;

        $articles[] = $parsed_article;
    }

    return $articles;
}

/**
 * Extract JSON from AI response content
 * 
 * This function attempts to extract JSON data from AI response content.
 * It looks for JSON between code fences (```json ... ```) or any JSON
 * object in the content. If the JSON is malformed, it attempts to fix
 * common issues like trailing commas and unquoted keys.
 * 
 * @param string $content AI response content
 * @return array|null Extracted JSON data or null if not found/invalid
 * 
 * @note First tries to find JSON between code fences
 * @note Then tries to find any JSON object in the content
 * @note Attempts to fix common JSON formatting issues
 * @note Returns null if no valid JSON is found
 * @see processProfileResponse() - Uses this for JSON output profiles
 * @note Used for extracting structured data from AI responses
 */
function extractJsonFromResponse($content) {
    // Try to find JSON between code fences
    if (preg_match('/```(?:json)?\s*({.*?})\s*```/s', $content, $matches)) {
        $json_str = $matches[1];
    } 
    // Then try to find any JSON object
    elseif (preg_match('/\{.*\}/s', $content, $matches)) {
        $json_str = $matches[0];
    } else {
        return null;
    }
    
    // Clean up the JSON string
    $json_str = trim($json_str);
    
    // Try to decode JSON
    $result = json_decode($json_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to fix common JSON issues
        $json_str = preg_replace('/,\s*([\]}])/m', '$1', $json_str); // Remove trailing commas
        $json_str = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_str); // Add quotes to keys
        $json_str = preg_replace('/:\s*\'([^\']*)\'/', ':"$1"', $json_str); // Replace single quotes with double quotes
        $json_str = preg_replace('/\s+/', ' ', $json_str); // Normalize whitespace
        
        $result = json_decode($json_str, true);
    }
    
    return $result;
}

/**
 * Convert array to YAML format
 * 
 * This function converts a PHP array to YAML format. It handles both
 * sequential and associative arrays, with proper indentation and formatting.
 * 
 * @param array $array Array to convert
 * @return string YAML formatted string
 * 
 * @note Uses recursive processing for nested arrays
 * @note Sequential arrays use "- " prefix
 * @note Associative arrays use "key: value" format
 * @note Strings are quoted for proper YAML formatting
 * @note Uses 2-space indentation for nested levels
 * @note Used for generating YAML output in some profiles
 */
function yaml_encode($array) {
    $yaml = '';
    $indent = 0;

    $process = function($data, $indent) use (&$process, &$yaml) {
        $spaces = str_repeat('  ', $indent);

        if (is_array($data)) {
            if (array_keys($data) === range(0, count($data) - 1)) {
                // Sequential array
                foreach ($data as $value) {
                    $yaml .= $spaces . "- ";
                    $process($value, $indent + 1);
                }
            } else {
                // Associative array
                foreach ($data as $key => $value) {
                    $yaml .= $spaces . $key . ": ";
                    $process($value, $indent + 1);
                }
            }
        } else {
            $yaml .= (is_string($data) ? '"' . $data . '"' : $data) . "\n";
        }
    };

    $process($array, $indent);
    return $yaml;
}

/**
 * Check if config.php is available and show configuration instructions if needed
 * 
 * This function checks for the existence of the configuration file and
 * returns an HTML message with instructions if the file is missing.
 * 
 * @return string HTML message about configuration status
 * 
 * @note Returns empty string if config.php exists
 * @note Returns error message with instructions if config.php is missing
 * @note Instructions include copying example file and editing settings
 * @note Used for displaying configuration status in web interface
 */
function checkConfigStatus() {
    if (file_exists('config.php')) {
        return '';
    } else {
        $message = '<div class="error">';
        $message .= '<strong>⚠️ Configuration file not found.</strong> Please create config.php with your settings.';
        $message .= '<div class="config-instructions">';
        $message .= '<p>Copy config.php.example to config.php and edit it with your API settings:</p>';
        $message .= '<pre>cp config.php.example config.php</pre>';
        $message .= '<p>Then edit config.php to set your LLM API endpoint and other options.</p>';
        $message .= '</div>';
        $message .= '</div>';
        return $message;
    }
}

/**
 * Remove markdown code fences from text
 * 
 * This function removes markdown code fences (```) from the beginning
 * and end of a text string, extracting the actual content.
 * 
 * @param string $text Text with markdown code fences
 * @return string Text without code fences
 * 
 * @note Removes opening fence (``` or ```lang)
 * @note Removes closing fence (```)
 * @note Trims whitespace from result
 * @note Used for extracting content from code blocks
 */
function removeMarkdownFence(string $text): string
{
    // Remove opening fence (``` or ```lang)
    $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $text);

    // Remove closing fence
    $text = preg_replace('/\s*```$/', '', $text);

    return trim($text);
}

/**
 * Convert basic markdown to HTML
 * 
 * This function converts basic markdown syntax to HTML. It supports
 * headers, lists, blockquotes, code blocks, horizontal rules, and
 * inline formatting (bold, italic, code, links, images).
 * 
 * @param string $markdown Markdown text to convert
 * @return string HTML output
 * 
 * @note Supports headers (1-6 levels)
 * @note Supports ordered and unordered lists
 * @note Supports blockquotes
 * @note Supports code blocks (```)
 * @note Supports horizontal rules
 * @note Supports inline formatting (bold, italic, code, links, images)
 * @note Escapes HTML entities for security
 * @note Used for rendering markdown content in responses
 */
function markdownToHtml($markdown) {
    // Remove markdown code fences if present
    $markdown = preg_replace('/^```(?:markdown)?\s*(.*?)\s*```$/s', '$1', $markdown);
    
    // Normalize line endings
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    
    // Escape HTML entities first
    $markdown = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
    
    // Split into lines for processing
    $lines = explode("\n", $markdown);
    $html = [];
    $inCodeBlock = false;
    $inList = false;
    $listType = '';
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);
        
        // Code blocks (```)
        if (preg_match('/^```/', $trimmed)) {
            if ($inCodeBlock) {
                $html[] = '</code></pre>';
                $inCodeBlock = false;
            } else {
                if ($inList) {
                    $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                    $inList = false;
                }
                $html[] = '<pre><code>';
                $inCodeBlock = true;
            }
            continue;
        }
        
        if ($inCodeBlock) {
            $html[] = $line;
            continue;
        }
        
        // Empty lines
        if ($trimmed === '') {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            continue;
        }
        
        // Headers
        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $level = strlen($matches[1]);
            $text = processInlineMarkdown($matches[2]);
            $html[] = "<h$level>$text</h$level>";
            continue;
        }
        
        // Unordered lists
        if (preg_match('/^[\*\-]\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList && $listType === 'ol') {
                    $html[] = '</ol>';
                }
                $html[] = '<ul>';
                $inList = true;
                $listType = 'ul';
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<li>$text</li>";
            continue;
        }
        
        // Ordered lists
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList && $listType === 'ul') {
                    $html[] = '</ul>';
                }
                $html[] = '<ol>';
                $inList = true;
                $listType = 'ol';
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<li>$text</li>";
            continue;
        }
        
        // Blockquotes
        if (preg_match('/^>\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<blockquote>$text</blockquote>";
            continue;
        }
        
        // Horizontal rule
        if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $trimmed)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $html[] = '<hr>';
            continue;
        }
        
        // Regular paragraph
        if ($inList) {
            $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
            $inList = false;
        }
        $text = processInlineMarkdown($trimmed);
        $html[] = "<p>$text</p>";
    }
    
    // Close any open lists
    if ($inList) {
        $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
    }
    
    // Close any open code blocks
    if ($inCodeBlock) {
        $html[] = '</code></pre>';
    }
    
    return implode("\n", $html);
}

/**
 * Process inline markdown formatting
 * 
 * This function converts inline markdown formatting to HTML. It handles
 * bold, italic, inline code, links, and images.
 * 
 * @param string $text Text with inline markdown
 * @return string HTML formatted text
 * 
 * @note Supports **bold** and __bold__
 * @note Supports *italic* and _italic_
 * @note Supports `inline code`
 * @note Supports [links](url)
 * @note Supports ![images](url)
 * @note Used by markdownToHtml() for inline formatting
 */
function processInlineMarkdown($text) {
    // Bold (**text** or __text__)
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
    
    // Italic (*text* or _text_)
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
    
    // Inline code (`code`)
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    
    // Links [text](url)
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
    
    // Images ![alt](url)
    $text = preg_replace('/!\[(.+?)\]\((.+?)\)/', '<img src="$2" alt="$1">', $text);
    
    return $text;
}

/**
 * Get the color associated with a probability level
 * 
 * This function returns a color code based on a probability percentage.
 * The color scale progresses from green (low probability) to red (high
 * probability), providing visual feedback for confidence levels.
 * 
 * @param int $probability Probability percentage (0-100)
 * @return string Hex color code for the probability level
 * 
 * @note Uses a traffic light color scheme for intuitive understanding
 * @note Color codes are in hexadecimal format (e.g., '#ef4444')
 * @note 0-39% = Green (low probability)
 * @note 40-59% = Blue (medium-low probability)
 * @note 60-79% = Orange (medium-high probability)
 * @note 80-100% = Red (high probability)
 */
function getProbabilityColor($probability) {
    if ($probability >= 80) return '#ef4444'; // red
    if ($probability >= 60) return '#f59e0b'; // orange
    if ($probability >= 40) return '#3b82f6'; // blue
    return '#10b981'; // green
}

/**
 * Load resource from JSON file
 * 
 * This function loads and parses a JSON configuration file. It handles
 * file existence checks, reading errors, and JSON parsing errors,
 * returning appropriate error messages for each case.
 * 
 * @param string $filename Path to the JSON file
 * @return array Decoded JSON data or error array
 * 
 * @note Checks if file exists before attempting to read
 * @note Handles file read errors gracefully
 * @note Validates JSON format and reports parsing errors
 * @note Returns ['error' => message] on failure
 * @note Used for loading profiles.json, languages.json, etc.
 * @see handleApiRequest() - Uses this for loading profiles
 * @see buildProfilePrompt() - Uses this for loading profiles and languages
 */
function loadResourceFromJson($filename) {
    // Check if resource file exists
    if (!file_exists($filename)) {
        $resource_name = ucfirst(str_replace('.json', '', $filename));
        return ['error' => $resource_name . ' configuration file not found'];
    }

    // Read and decode JSON file
    $json_content = file_get_contents($filename);
    if ($json_content === false) {
        return ['error' => 'Failed to read ' . $filename . ' configuration file'];
    }

    // Decode JSON content
    $resource_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON format in ' . $filename . ' configuration: ' . json_last_error_msg()];
    }

    // Return the decoded resource data
    return $resource_data;
}

/**
 * Get syntax highlighting function for a given language
 * 
 * This function maps programming languages to their corresponding
 * JavaScript syntax highlighting functions. It's used to determine
 * which highlighting function to call for different code types.
 * 
 * @param string $language Language code or file extension
 * @return string JavaScript function name or empty string if not available
 * 
 * @note Supports JSON, YAML, Markdown, and XML
 * @note Language codes are case-insensitive
 * @note Returns empty string for unsupported languages
 * @see extractCodeFenceInfo() - Uses this to get highlighting function
 */
function getHighlightFunction($language) {
    $highlight_functions = [
        'json' => 'jsonSyntaxHighlight',
        'yaml' => 'yamlSyntaxHighlight',
        'yml' => 'yamlSyntaxHighlight',
        'markdown' => 'markdownSyntaxHighlight',
        'md' => 'markdownSyntaxHighlight',
        'xml' => 'xmlSyntaxHighlight'
    ];

    $language = strtolower($language);
    return isset($highlight_functions[$language]) ? $highlight_functions[$language] : '';
}

/**
 * Extract code fence information from text
 * 
 * This function analyzes text to detect markdown code fences and extract
 * the language type and content. It determines which syntax highlighting
 * function should be used for the code block.
 * 
 * @param string $text Text to analyze
 * @param string $default Default type when no fence is found (default: 'text')
 * @return array Array with 'type', 'function', and 'text' keys
 * 
 * @note Detects ```language code fences
 * @note Extracts language type from fence (e.g., ```json)
 * @note Returns empty type if no fence found
 * @note Uses default type when no fence is detected
 * @note Returns highlighting function name for the language
 * @see getHighlightFunction() - Used to get function name
 */
function extractCodeFenceInfo($text, $default = 'text') {
    $result = [
        'type' => '',
        'function' => '',
        'text' => $text
    ];

    if (preg_match('/^```([a-zA-Z0-9_-]*)\s*(.*?)\s*```$/s', $text, $matches)) {
        $result['type'] = !empty($matches[1]) ? strtolower($matches[1]) : '';
        $result['text'] = $matches[2];
        $result['function'] = getHighlightFunction($result['type']);
    } else {
        // Set default type if no fence found
        $result['type'] = $default;
        $result['function'] = getHighlightFunction($default);
    }

    return $result;
}
?>
