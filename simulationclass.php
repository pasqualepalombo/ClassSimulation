<?php
 // Start the session once at the beginning

/**
 * Creazione della classe simulata
 *
 * @package    class_simulation
 * @copyright  2024 Pasquale Palombo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once (__DIR__.'/webserviceBN.php');

# FORM HANDLING
 // Inizializza la sessione

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['classSelection'])) {
        $_SESSION['classSelection'] = $_POST['classSelection']; // Salva nella sessione
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filepath'])) {
    // Questa sezione gestisce l'AJAX per generate_student_options
    $filepath = $_POST['filepath'];

    if (file_exists($filepath)) {
        generate_student_options($filepath);
    } else {
        echo '<option value="no_student">File not found</option>';
    }

    // Termina l'esecuzione per evitare di eseguire altro codice della pagina
    exit;
}
$tableHTML = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['class_settings_btn'])) {
        $studentsNumber = $_POST['studentsNumber'];
        $distribution = $_POST['distribution'];
        if ($distribution=="gaussian"){
            $median = $_POST['median'];
            $standardeviation = $_POST['standardDeviation'];
            $skewness = $_POST['skewness'];
            create_new_class_gaussian($studentsNumber, $median, $standardeviation, $skewness);
        }
        if ($distribution=="random"){
            create_new_class_random($studentsNumber);
        }
        if ($distribution=="json"){
            $feedback = process_uploaded_file($_FILES['fileUpload']);
            echo $feedback; // Mostra il feedback (successo o errore)
        }
    }
    elseif (isset($_POST['pas_settings_btn'])) {
        $m0Model = $_POST['m0Model'];
        $peerNumber = (int)$_POST['peerNumber'];
        $classSelection = $_POST['classSelection'];
        $randomMin = isset($_POST['randomMin']) ? (float)$_POST['randomMin'] : 0.0;
        $randomMax = isset($_POST['randomMax']) ? (float)$_POST['randomMax'] : 0.1;

        // Calcola il valore casuale
        $randomness;
        if ($randomMin < $randomMax) {
            $randomness = mt_rand($randomMin * 100, $randomMax * 100) / 100; // Genera un numero tra min e max
        } else {
            $randomness = $randomMin; // Fallback se i valori non sono validi
        }
        if ($m0Model=="flat"){create_flat_allocation_on_that_class($peerNumber, $classSelection, $randomness);}
        elseif ($m0Model=="random"){create_random_allocation_on_that_class($peerNumber, $classSelection, $randomness);}
    }
    elseif (isset($_POST['teacher_settings_btn'])) {
        $sessionSelection = $_POST['sessionSelection'];
        $gradeOption = $_POST['gradeOption'];
        $randomMin = $_POST['randomMin'];
        $randomMax = $_POST['randomMax'];
        // Se non è selezionata, rimane 0, sennò prende l'id che non sarà mai 0
        $submissionOption = 0;
        // Gestione del Grade Option
        if ($gradeOption === "submission" && isset($_POST['submissionOption'])) {
            $submissionOption = $_POST['submissionOption'];
            echo $submissionOption;
        }
        
        let_the_teacher_judge($sessionSelection, $gradeOption, $randomMax, $randomMin, $submissionOption);
    }
}

function process_uploaded_file($uploadedFile) {
    // Controlla se il file è stato caricato correttamente
    if (isset($uploadedFile) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $uploadedFile['tmp_name'];
        $fileName = $uploadedFile['name'];
        $fileType = $uploadedFile['type'];

        // Verifica che il file sia un JSON
        $allowedMimeTypes = ['application/json', 'text/plain'];
        if (!in_array($fileType, $allowedMimeTypes)) {
            return "<p class='text-danger'>Invalid file type. Only JSON files are allowed.</p>";
        }

        // Leggi il contenuto del file JSON
        $jsonContent = file_get_contents($fileTmpPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return "<p class='text-danger'>Error decoding JSON: " . json_last_error_msg() . "</p>";
        }

        // Rimuovi le parti inutili dal JSON
        if (
            isset($data[0]['type']) && $data[0]['type'] === 'header' &&
            isset($data[1]['type']) && $data[1]['type'] === 'database' &&
            isset($data[2]['type']) && $data[2]['type'] === 'table' &&
            isset($data[2]['data'])
        ) {
            $cleanedData = $data[2]['data']; // Prendi solo i dati
        } else {
            return "<p class='text-danger'>Invalid JSON structure.</p>";
        }

        // Rimuovi i campi indesiderati (id, courseid, advworkid)
        foreach ($cleanedData as &$record) {
            unset($record['id'], $record['courseid'], $record['advworkid']);
        }

        // Mantieni solo gli ultimi 18 elementi per ogni userid
        $groupedData = [];
        foreach ($cleanedData as $record) {
            $userId = $record['userid'];
            $groupedData[$userId][] = $record;
        }

        $finalData = [];
        foreach ($groupedData as $userId => $records) {
            $finalData = array_merge($finalData, array_slice($records, -18));
        }

        // Trova un nome unico per la cartella
        $baseDir = 'simulatedclass/';
        $classNumber = 1;

        while (file_exists($baseDir . "uploaded_class_$classNumber")) {
            $classNumber++;
        }

        $newClassDir = $baseDir . "uploaded_class_$classNumber/";
        mkdir($newClassDir, 0777, true); // Crea la directory

        // Salva il file nella nuova cartella con il suffisso _mr.json
        $newFilePath = $newClassDir . "uploaded_class_$classNumber" . "_mr.json";
        $cleanedJsonContent = json_encode($finalData, JSON_PRETTY_PRINT);

        if (file_put_contents($newFilePath, $cleanedJsonContent)) {
        } else {
            return "<p class='text-danger'>Error saving the file.</p>";
        }
    } else {
        return "<p class='text-danger'>Error during file upload. Error code: {$uploadedFile['error']}</p>";
    }
}

function create_flat_allocation_on_that_class($peerNumber, $classSelection, $randomness) {
    // Ottieni gli studenti ordinati usando la funzione già esistente
    $ordered_students = models_ordering_for_flat($classSelection);

    // Estrai gli 'userid' distinti
    $userids = [];
    foreach ($ordered_students as $key => $student_group) {
        // Cicliamo su ogni gruppo di studenti per estrarre gli 'userid'
        foreach ($student_group as $student) {
            if (!in_array($student['userid'], $userids)) {
                $userids[] = $student['userid'];
            }
        }
    }

    // Inizializza l'array per tracciare quante volte un 'userid' è stato scelto
    $peer_assignment_count = array_fill_keys($userids, 0);

    // JSON da costruire
    $json_output = [
        "parameters" => [
            "strategy" => "maxEntropy",
            "termination" => "corrected30",
            "mapping" => "weightedSum",
            "domain" => [
                1,
                0.95,
                0.85,
                0.75,
                0.65,
                0.55,
                0
            ]
        ],
        "peer-assessments" => []
    ];

    // Cicla per ogni userid
    foreach ($userids as $current_userid) {
        // Crea l'array $remain_student con tutti gli id tranne quello corrente
        $remain_students = array_filter($userids, function($userid) use ($current_userid) {
            return $userid !== $current_userid;
        });

        // Crea un array di peer assegnati per il current_userid
        $assigned_peers = [];

        // Limita la selezione dei peer per ogni userid
        $attempts = 0; // Contatore per evitare cicli infiniti
        while (count($assigned_peers) < $peerNumber && $attempts < 100) {
            // Seleziona un peer casuale tra i restanti che non ha ancora raggiunto il limite
            $available_peers = array_filter($remain_students, function($peer) use ($peer_assignment_count, $peerNumber) {
                return $peer_assignment_count[$peer] < $peerNumber;
            });

            // Se non ci sono peer disponibili, esci dal ciclo
            if (empty($available_peers)) {
                break;
            }

            // Scegli un peer casuale
            $random_peer = $available_peers[array_rand($available_peers)];

            // Aggiungi il peer alla lista di assegnazione per il current_userid
            $assigned_peers[$random_peer] = "0.00";

            // Incrementa il contatore di assegnazioni per questo peer
            $peer_assignment_count[$random_peer]++;

            // Rimuovi il peer selezionato dalla lista dei restanti
            $remain_students = array_filter($remain_students, function($userid) use ($random_peer) {
                return $userid !== $random_peer;
            });

            $attempts++;
        }

        // Aggiungi i peer assegnati al JSON
        $json_output["peer-assessments"][$current_userid] = $assigned_peers;
    }

    $filePath = "simulatedclass/$classSelection/";
    $fileName = "{$classSelection}_peerassessment.json";

    // Crea la directory se non esiste
    if (!file_exists($filePath)) {
        mkdir($filePath, 0777, true); // Crea la directory con permessi completi
    }

    // Salva il file JSON
    $json_string = json_encode($json_output, JSON_PRETTY_PRINT);
    file_put_contents($filePath . $fileName, $json_string);

    // Creazione del modello m0 
    send_data($filePath, $classSelection);
    
    //Assegnazione voti
    make_the_peer_assessment_session($classSelection, $randomness);

    // Creazione del modello M1
    send_data_for_model($filePath, $classSelection);
}

function create_random_allocation_on_that_class($peerNumber, $classSelection, $randomness) {
    // Ottieni tutti gli studenti usando una funzione esistente
    $userids = get_distinct_userids($classSelection); // Funzione per ottenere gli 'userid' distinti

    // Inizializza l'array per tracciare quante volte un 'userid' è stato scelto
    $peer_assignment_count = array_fill_keys($userids, 0);

    // JSON da costruire
    $json_output = [
        "parameters" => [
            "strategy" => "maxEntropy",
            "termination" => "corrected30",
            "mapping" => "weightedSum",
            "domain" => [
                1,
                0.95,
                0.85,
                0.75,
                0.65,
                0.55,
                0
            ]
        ],
        "peer-assessments" => []
    ];

    // Cicla per ogni userid
    foreach ($userids as $current_userid) {
        // Crea l'array $remain_student con tutti gli id tranne quello corrente
        $remain_students = array_filter($userids, function($userid) use ($current_userid) {
            return $userid !== $current_userid;
        });

        // Crea un array di peer assegnati per il current_userid
        $assigned_peers = [];

        // Limita la selezione dei peer per ogni userid
        $attempts = 0; // Contatore per evitare cicli infiniti
        while (count($assigned_peers) < $peerNumber && $attempts < 100) {
            // Seleziona un peer casuale tra i restanti che non ha ancora raggiunto il limite
            $available_peers = array_filter($remain_students, function($peer) use ($peer_assignment_count, $peerNumber) {
                return $peer_assignment_count[$peer] < $peerNumber;
            });

            // Se non ci sono peer disponibili, esci dal ciclo
            if (empty($available_peers)) {
                break;
            }

            // Scegli un peer casuale
            $random_peer = $available_peers[array_rand($available_peers)];

            // Aggiungi il peer alla lista di assegnazione per il current_userid
            $assigned_peers[$random_peer] = "0.00";

            // Incrementa il contatore di assegnazioni per questo peer
            $peer_assignment_count[$random_peer]++;

            // Rimuovi il peer selezionato dalla lista dei restanti
            $remain_students = array_filter($remain_students, function($userid) use ($random_peer) {
                return $userid !== $random_peer;
            });

            $attempts++;
        }

        // Aggiungi i peer assegnati al JSON
        $json_output["peer-assessments"][$current_userid] = $assigned_peers;
    }

    $filePath = "simulatedclass/$classSelection/";
    $fileName = "{$classSelection}_peerassessment.json";

    // Crea la directory se non esiste
    if (!file_exists($filePath)) {
        mkdir($filePath, 0777, true); // Crea la directory con permessi completi
    }

    // Salva il file JSON
    $json_string = json_encode($json_output, JSON_PRETTY_PRINT);
    file_put_contents($filePath . $fileName, $json_string);

    // Creazione del modello m0 
    send_data($filePath, $classSelection);

    // Assegnazione voti
    make_the_peer_assessment_session($classSelection, $randomness);

    // Creazione del modello M1
    send_data_for_model($filePath, $classSelection);
}

// Funzione per ottenere tutti gli 'userid' distinti
function get_distinct_userids($classSelection) {
    $filePath = "simulatedclass/$classSelection/{$classSelection}_mr.json";
    $data = json_decode(file_get_contents($filePath), true);

    // Estrarre gli 'userid' dal file
    return array_unique(array_column($data, 'userid'));
}

function make_the_peer_assessment_session($classSelection, $randomness) {
    
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_peerassessment.json";
    
    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    if (!$data || !isset($data['peer-assessments'])) {
        throw new Exception("Struttura JSON non valida nel file $filePath.");
    }

    // Itera su ogni ID nella sezione "peer-assessments"
    foreach ($data['peer-assessments'] as $userId => $assessments) {
        foreach ($assessments as $assignedId => $score) {
            // Calcola il voto utilizzando calcolate_score_k_by_j()
            $newScore = calcolate_score_k_by_j($userId, $assignedId, $classSelection, $randomness);
            
            // Aggiorna il voto nel JSON
            $data['peer-assessments'][$userId][$assignedId] = $newScore;
        }
    }
    // Scrivi i dati modificati nuovamente nel file JSON
    $updatedJsonData = json_encode($data, JSON_PRETTY_PRINT);
    
    // Salva nel file
    if (file_put_contents($filePath, $updatedJsonData) === false) {
        throw new Exception("Impossibile salvare il file $filePath.");
    }
}

function calcolate_score_k_by_j($userId, $assignedId, $classSelection, $randomness) {
    // Recupera i valori k_real e j_sim
    $k_real = get_k_real($userId, $classSelection);
    $j_sim = get_j_sim($assignedId, $classSelection);

    // Calcola un voto base come la media ponderata tra k_real e j_sim
    $base_score = ($k_real + $j_sim) / 2;

    // Calcola una piccola variazione casuale attorno al voto base
    $random_factor = 1 + (rand(-100, 100) / 10000) * $randomness;

    // Applica la variabilità al voto
    $score = $base_score * $random_factor;

    // Assicurati che il punteggio finale sia tra 0 e 1
    $score = max(0, min(1, $score));

    // Ritorna il voto finale
    return $score;
}


function get_k_real($userID, $classSelection) {
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_mr.json";

    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    if (!$data || !is_array($data)) {
        throw new Exception("Struttura JSON non valida nel file $filePath.");
    }

    // Cerca il valore di capabilityoverallvalue per l'utente specifico
    foreach ($data as $entry) {
        if (
            isset($entry['userid'], $entry['capabilityid'], $entry['domainvalueid'], $entry['capabilityoverallvalue']) &&
            $entry['userid'] == $userID &&
            $entry['capabilityid'] == "1" &&
            $entry['domainvalueid'] == "1"
        ) {
            return $entry['capabilityoverallvalue'];
        }
    }
}

function get_j_sim($userID, $classSelection) {
    // Percorso del file JSON
    $filePath = "simulatedclass/{$classSelection}/{$classSelection}_m0.json";

    // Verifica se il file esiste
    if (!file_exists($filePath)) {
        throw new Exception("File $filePath non trovato.");
    }

    // Leggi il contenuto del file
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    // Debug per verificare la struttura del JSON
    if (!$data) {
        throw new Exception("Errore nel parsing del JSON: " . json_last_error_msg());
    }

    // Verifica la presenza della chiave 'student-models'
    if (!isset($data['student-models'])) {
        throw new Exception("Chiave 'student-models' non trovata nel file $filePath. Contenuto del JSON: " . json_encode($data));
    }

    // Verifica se l'utente specificato esiste
    if (!isset($data['student-models'][$userID])) {
        throw new Exception("Dati per l'utente con ID $userID non trovati.");
    }

    // Recupera i dati dello studente
    $studentData = $data['student-models'][$userID];

    // Verifica se il campo 'J' esiste per l'utente specificato
    if (isset($studentData['J']['value'])) {
        return $studentData['J']['value']; // Restituisce il valore di 'J'
    } else {
        throw new Exception("Valore 'J' non trovato per l'utente con ID $userID.");
    }
}




function models_ordering_for_flat($classSelection) {
    // Costruisci il percorso del file JSON
    $filePath = "simulatedclass/$classSelection/{$classSelection}_mr.json";

    // Controlla se il file esiste
    if (!file_exists($filePath)) {
        echo "File not found: $filePath";
        return;
    }

    // Leggi il contenuto del file JSON
    $jsonData = file_get_contents($filePath);
    $data = json_decode($jsonData, true);

    // Controllo di validità del contenuto
    if (!$data || !is_array($data)) {
        echo "Invalid JSON data.";
        return;
    }

    // Raggruppa i dati per userid
    $groupedData = [];
    foreach ($data as $entry) {
        $userid = $entry['userid'];
        $groupedData[$userid][] = $entry;
    }

    // Estrai il valore J per ogni utente
    $userValues = [];
    foreach ($groupedData as $userid => $entries) {
        foreach ($entries as $entry) {
            if ($entry['domainvalueid'] == "1" && $entry['capabilityid'] == "2") {
                $userValues[$userid] = floatval($entry['capabilityoverallvalue']);
                break;
            }
        }
    }

    // Ordina gli utenti per il valore J
    asort($userValues);

    // Riorganizza i dati ordinati
    $orderedData = [];
    foreach (array_keys($userValues) as $userid) {
        $orderedData[$userid] = $groupedData[$userid];
    }

    // Puoi ora restituire $orderedData per ulteriore elaborazione
    return $orderedData;
}

function create_new_class_gaussian($studentsNumber, $median, $standardeviation, $skewness) {
    $data = [];
    $startUserId = 1; // Inizia da 1 come richiesto

    // Genera gli array di K, J e C
    $k_values = generate_gaussian_distribution($studentsNumber, 0.5, 0.1); // Distribuzione normale per K
    $j_values = array_map(function ($k) {
        return generate_with_variation($k, 0.25); // ±25% di K
    }, $k_values);
    $c_values = array_map(function ($k) {
        return generate_with_variation($k, 0.25); // ±25% di K
    }, $k_values);

    // Itera sugli studenti per costruire la struttura dei dati
    for ($i = 0; $i < $studentsNumber; $i++) {
        $userId = $startUserId + $i;

        foreach ([1 => $k_values[$i], 2 => $j_values[$i], 3 => $c_values[$i]] as $capabilityId => $capabilityValue) {
            // Genera probabilità normalizzate per i domini
            $domainProbabilities = generate_normalized_probabilities(6);

            // Calcola il valore complessivo di capacità
            $capabilityOverallValue = calculate_capability_value($domainProbabilities);

            // Normalizza le probabilità per garantire che la somma sia 1
            $totalProbability = array_sum($domainProbabilities);
            $normalizedProbabilities = array_map(function ($probability) use ($totalProbability) {
                return $probability / $totalProbability;
            }, $domainProbabilities);

            // Assegna il grade in base a `capabilityOverallValue`
            $grade = assign_grade($capabilityOverallValue);

            // Crea 6 record per ogni capabilityid
            foreach ($normalizedProbabilities as $domainValueId => $probability) {
                $data[] = [
                    "userid" => (string)$userId,
                    "capabilityid" => (string)$capabilityId,
                    "domainvalueid" => (string)($domainValueId + 1),
                    "probability" => number_format($probability, 5),
                    "capabilityoverallgrade" => $grade,
                    "capabilityoverallvalue" => number_format($capabilityOverallValue, 5),
                    "iscumulated" => "0"
                ];
            }
        }
    }

    // Salva i dati in un file JSON
    create_new_class('gaussian', $data);
}

// Funzione per generare una distribuzione normale
function generate_gaussian_distribution($n, $mean, $stdDev) {
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[] = generate_gaussian($mean, $stdDev);
    }
    return $values;
}

// Funzione per generare un valore gaussiano
function generate_gaussian($mean, $stdDev) {
    $u1 = mt_rand() / mt_getrandmax();
    $u2 = mt_rand() / mt_getrandmax();
    return $mean + $stdDev * sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
}

// Funzione per variare un valore di ±percentuale
function generate_with_variation($value, $percentage) {
    $variation = $value * $percentage;
    return max(0.3, min(1.0, $value + generate_gaussian(0, $variation))); // Limita tra 0.3 e 1
}

// Funzione per calcolare il valore complessivo di capacità
function calculate_capability_value($probabilities) {
    $weights = [0.975, 0.9, 0.8, 0.7, 0.6, 0.275]; // Pesi per i range
    $sum = 0;

    foreach ($probabilities as $index => $probability) {
        $sum += $weights[$index] * $probability;
    }

    return $sum;
}

// Funzione per assegnare un grade in base a un valore
function assign_grade($value) {
    if ($value >= 0.95) {
        return "A";
    } elseif ($value >= 0.85) {
        return "B";
    } elseif ($value >= 0.75) {
        return "C";
    } elseif ($value >= 0.65) {
        return "D";
    } elseif ($value >= 0.55) {
        return "E";
    } else {
        return "F";
    }
}

// Funzione per generare probabilità normalizzate
function generate_normalized_probabilities($count) {
    $values = [];
    $sum = 0;

    for ($i = 0; $i < $count; $i++) {
        $value = mt_rand(1, 1000) / 1000; // Valori casuali tra 0 e 1
        $values[] = $value;
        $sum += $value;
    }

    // Normalizza i valori
    return array_map(function ($value) use ($sum) {
        return $value / $sum;
    }, $values);
}

function create_new_class_random($studentsNumber) {
    $data = [];
    $startUserId = 1; // Inizia da 1 come richiesto

    // Genera gli array di K, J e C casuali
    $k_values = generate_random_values($studentsNumber); // Genera valori casuali per K
    $j_values = array_map(function ($k) {
        return generate_with_variation_random($k, 0.25); // ±25% di K
    }, $k_values);
    $c_values = array_map(function ($k) {
        return generate_with_variation_random($k, 0.25); // ±25% di K
    }, $k_values);

    // Itera sugli studenti per costruire la struttura dei dati
    for ($i = 0; $i < $studentsNumber; $i++) {
        $userId = $startUserId + $i;

        foreach ([1 => $k_values[$i], 2 => $j_values[$i], 3 => $c_values[$i]] as $capabilityId => $capabilityValue) {
            // Genera probabilità normalizzate per i domini
            $domainProbabilities = generate_normalized_probabilities(6); // Riutilizza funzione esistente

            // Calcola il valore complessivo di capacità
            $capabilityOverallValue = calculate_capability_value($domainProbabilities); // Riutilizza funzione esistente

            // Assegna il grade in base a `capabilityOverallValue`
            $grade = assign_grade($capabilityOverallValue); // Riutilizza funzione esistente

            // Crea 6 record per ogni capabilityid
            foreach ($domainProbabilities as $domainValueId => $probability) {
                $data[] = [
                    "userid" => (string)$userId,
                    "capabilityid" => (string)$capabilityId,
                    "domainvalueid" => (string)($domainValueId + 1),
                    "probability" => number_format($probability, 5),
                    "capabilityoverallgrade" => $grade,
                    "capabilityoverallvalue" => number_format($capabilityOverallValue, 5),
                    "iscumulated" => "0"
                ];
            }
        }
    }

    // Salvataggio in un file JSON
    create_new_class('random', $data);
}

// Genera valori casuali per K
function generate_random_values($n) {
    $values = [];
    for ($i = 0; $i < $n; $i++) {
        $values[] = mt_rand() / mt_getrandmax(); // Valori casuali tra 0 e 1
    }
    return $values;
}

// Genera variazioni casuali di ±percentuale
function generate_with_variation_random($value, $percentage) {
    $variation = $value * $percentage * (mt_rand(0, 1) ? 1 : -1);
    return max(0.3, min(1.0, $value + $variation)); // Limita tra 0.3 e 1
}

function create_new_class($distribution, $data) {
    // Inizializza il contatore per il numero incrementale
    $counter = 1;

    // Crea il prefisso della cartella utilizzando la stringa $distribution e il contatore
    $baseFolderName = "simulatedclass/{$distribution}_class_{$counter}";

    // Verifica se la cartella esiste già
    while (is_dir($baseFolderName)) {
        $counter++;  // Incrementa il contatore
        $baseFolderName = "simulatedclass/{$distribution}_class_{$counter}";  // Crea un nuovo nome per la cartella
    }

    // Crea la cartella con il nome corretto
    mkdir($baseFolderName, 0777, true);  // Aggiunto il flag 'true' per creare anche le cartelle superiori se necessario

    // Definisci il percorso del file JSON
    $jsonFile = $baseFolderName . "/" . $distribution . "_class_{$counter}_mr.json";

    // Scrivi il file JSON nella cartella appena creata
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

    // Restituisce il nome della cartella creata
    return $baseFolderName;
}

# PEERASESSMENTSESSION
function generate_class_options() {
    $folder_path = 'simulatedclass';
    $directories = array_filter(glob($folder_path . '/*'), 'is_dir');
    $classes = [];

    foreach ($directories as $dir) {
        if (preg_match('/^(.*)_class_(\d+)$/', basename($dir), $matches)) {
            $classes[] = [
                'prefix' => $matches[1],
                'id' => $matches[2]
            ];
        }
    }

    if (empty($classes)) {
        echo '<option value="no_class">no class available</option>';
    } else {
        foreach ($classes as $class) {
            echo '<option value="' . $class['prefix'] . '_class_' . $class['id'] . '">' . $class['prefix'] . '_class_' . $class['id'] . '</option>';
        }
    }
}

# TEACHER SETTINGS
function generate_session_options() {
    $folder_path = 'simulatedclass';
    $directories = array_filter(glob($folder_path . '/*'), 'is_dir');
    $sessions = [];

    foreach ($directories as $dir) {
        $files = glob($dir . '/*_class_*_m1*.json');
        
        foreach ($files as $file) {
            if (preg_match('/^(.*)_class_(\d+)_m1(?:_(\d+))?\.json$/', basename($file), $matches)) {
                $sessions[] = [
                    'file_path' => $file,
                    'prefix' => $matches[1],
                    'class_id' => $matches[2],
                    'session_id' => isset($matches[3]) ? $matches[3] : 1 // Default session_id to 1 if not present
                ];
            }
        }
    }

    if (empty($sessions)) {
        echo '<option value="no_session">no session available</option>';
    } else {
        foreach ($sessions as $session) {
            $text = $session['prefix'] . '_class_' . $session['class_id'] . '_session_' . $session['session_id'];
            $value = $session['file_path'];
            echo '<option value="' . $value . '">' . $text . '</option>';
        }
    }
}

function generate_student_options($filepath = null) {
    if (!$filepath || !file_exists($filepath)) {
        echo '<option value="no_student">No students available</option>';
        return;
    }

    // Leggi il contenuto del file JSON
    $data = json_decode(file_get_contents($filepath), true);

    // Verifica se la struttura del JSON contiene student-models
    if (!isset($data['student-models']) || !is_array($data['student-models'])) {
        echo '<option value="no_student">No students available</option>';
        return;
    }

    // Itera sugli ID dentro student-models
    foreach (array_keys($data['student-models']) as $student_id) {
        echo '<option value="' . htmlspecialchars($student_id) . '">Student ' . htmlspecialchars($student_id) . '</option>';
    }
}

function let_the_teacher_judge($sessionSelection, $gradeOption, $randomMax, $randomMin, $submissionOption) {
    // Rimuovi l'ultima parte del percorso (es. m1.json)
    $originalFilePath = $sessionSelection;
    $filePath = preg_replace('/_m1\.json$/', '_peerassessment.json', $sessionSelection);

    // Controlla se il file esiste
    if (!file_exists($filePath)) {
        echo "File not found: " . htmlspecialchars($filePath);
        return false;
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return false;
    }

    // Aggiungi 'teacher-grades' usando una funzione esterna
    $userids = read_userid_from_json($originalFilePath);
    $realFilePath = preg_replace('/_m1\.json$/', '_mr.json', $sessionSelection);

    // Calcola il valore casuale
    if ($randomMin < $randomMax) {
        $randomness = mt_rand($randomMin * 100, $randomMax * 100) / 100; // Genera un numero tra min e max
    } else {
        $randomness = $randomMin; // Fallback se i valori non sono validi
    }

    $newTeacherGrades = calculate_grade_by_teacher($gradeOption, $randomMax, $randomMin, $submissionOption, $realFilePath, $userids, $randomness);

    // Gestisci 'teacher-grades' in append
    if (!isset($data['teacher-grades'])) {
        $data['teacher-grades'] = []; // Se non esiste, crea l'array vuoto
    }

    // Unisci i nuovi teacher-grades con quelli esistenti (in append)
    foreach ($newTeacherGrades as $userId => $grade) {
        $data['teacher-grades'][$userId] = $grade;
    }

    // Scrivi il nuovo contenuto JSON nel file
    $newJsonContent = json_encode($data, JSON_PRETTY_PRINT);
    if ($newJsonContent === false) {
        echo "Error encoding updated JSON.";
        return false;
    }

    if (file_put_contents($filePath, $newJsonContent) === false) {
        echo "Error writing updated JSON to file.";
        return false;
    }

    if (preg_match('#^(simulatedclass/[^/]+_class_\d+)/[^/]+_class_\d+_m\d+\.json$#', $originalFilePath, $matches)) {
        $filePath = $matches[1] . '/'; // Parte directory
        $classSelection = basename($matches[1]); // Solo nome classe
    } else {
        // Gestione errore se il formato non è corretto
        $filePath = null;
        $classSelection = null;
        echo "Invalid file path format.";
    }

    send_data_for_model($filePath, $classSelection);

    global $tableHTML;
    $tableHTML = display_the_class_overview($filePath, $classSelection);
}


// Funzione per calcolare i teacher grades
function calculate_grade_by_teacher($gradeOption, $randomMax, $randomMin, $submissionOption, $realFilePath, $userids, $randomness) {
    
    $teacherGrades = [];
    
    switch ($gradeOption) {
        case 'random':
            $randomKey = array_rand($userids);
            $k_student = find_capability_overall_value($realFilePath, $userids[$randomKey]);
            $user = $userids[$randomKey];
            $value = calculate_score_by_teacher($k_student, $randomness);
            // Genera valori casuali per i teacher grades
            $teacherGrades = [
                $user => $value
            ];
            break;

        case 'most_suitable':
            $result = find_capability_overall_c_value($realFilePath);
            $user = $result['userid'];
            $k_student = find_capability_overall_value($realFilePath, $user);
            $value = calculate_score_by_teacher($k_student, $randomness);
            $teacherGrades = [
                $user => $value
            ];
            break;

        case 'submission':
            // Usa il valore di $submissionOption per creare i teacher grades
            $k_student = find_capability_overall_value($realFilePath, $submissionOption);
            $value = calculate_score_by_teacher($k_student, $randomness);
            // Genera valori casuali per i teacher grades
            $teacherGrades = [
                $submissionOption => $value
            ];
            break;

        default:
            echo "Invalid grade option.";
            break;
    }
            
    return $teacherGrades;
}

function calculate_score_by_teacher($k_real, $randomness){
    // Recupera i valori k_real e j_teacher
    $j_teacher = 1;
    // Calcola un voto base come la media ponderata tra k_real e j_teacher
    $base_score = ($k_real + $j_teacher) / 2;

    // Calcola una piccola variazione casuale attorno al voto base
    $random_factor = 1 + (rand(-100, 100) / 10000) * $randomness;

    // Applica la variabilità al voto
    $score = $base_score * $random_factor;

    // Assicurati che il punteggio finale sia tra 0 e 1
    $score = max(0, min(1, $score));

    // Ritorna il voto finale
    return $score;
}

function read_userid_from_json($filepath) {
    // Controlla se il file esiste
    if (!file_exists($filepath)) {
        echo "File not found: " . htmlspecialchars($filepath);
        return [];
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($filepath);
    $data = json_decode($jsonContent, true);

    // Controlla errori di decodifica
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return [];
    }

    // Controlla se 'student-models' esiste
    if (!isset($data['student-models']) || !is_array($data['student-models'])) {
        echo "'student-models' not found in JSON or not an array.";
        return [];
    }

    // Estrai le chiavi (userID)
    $userIDs = array_keys($data['student-models']);

    // Restituisci gli userID come array
    return $userIDs;
}

function find_capability_overall_value($realFilePath, $userId) {
    // Controlla se il file esiste
    if (!file_exists($realFilePath)) {
        echo "File not found: " . htmlspecialchars($realFilePath);
        return null;
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($realFilePath);
    $data = json_decode($jsonContent, true);

    // Controlla errori di decodifica
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return null;
    }

    // Cerca l'elemento con userid, capabilityid e domainvalueid corrispondenti
    foreach ($data as $entry) {
        if (
            isset($entry['userid'], $entry['capabilityid'], $entry['domainvalueid'], $entry['capabilityoverallvalue']) &&
            $entry['userid'] == $userId &&
            $entry['capabilityid'] == "1" &&
            $entry['domainvalueid'] == "1"
        ) {
            return $entry['capabilityoverallvalue'];
        }
    }

    // Se nessun elemento corrisponde
    echo "No matching entry found for userid: $userId with capabilityid=1 and domainvalueid=1.";
    return null;
}

function find_capability_overall_c_value($realFilePath) {
    // Controlla se il file esiste
    if (!file_exists($realFilePath)) {
        echo "File not found: " . htmlspecialchars($realFilePath);
        return null;
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($realFilePath);
    $data = json_decode($jsonContent, true);

    // Controlla errori di decodifica
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return null;
    }

    $highestValue = null;
    $highestUserId = null;

    // Cerca l'elemento con il valore capabilityoverallvalue più alto
    foreach ($data as $entry) {
        if (
            isset($entry['userid'], $entry['capabilityid'], $entry['domainvalueid'], $entry['capabilityoverallvalue']) &&
            $entry['capabilityid'] == "3" &&
            $entry['domainvalueid'] == "1"
        ) {
            $currentValue = floatval($entry['capabilityoverallvalue']);
            if ($highestValue === null || $currentValue > $highestValue) {
                $highestValue = $currentValue;
                $highestUserId = $entry['userid'];
            }
        }
    }

    // Se non c'è alcuna corrispondenza
    if ($highestUserId === null) {
        echo "No matching entry found for capabilityid=3 and domainvalueid=1.";
        return null;
    }

    // Restituisci un array con userid e capabilityoverallvalue
    return [
        'userid' => $highestUserId,
        'capabilityoverallvalue' => $highestValue
    ];
}

function display_the_class_overview($filePath, $classSelection) {
    // Definisci i file
    $filemr = $filePath . $classSelection . "_mr.json";
    $useridmr = get_userids_and_values_from_mr($filemr);
    $userIds = array_filter(array_keys($useridmr), 'is_numeric');

    $filem0 = $filePath . $classSelection . "_m0.json";
    $m0data = get_userids_and_values_from_mn($filem0);
    $filem1 = $filePath . $classSelection . "_m1.json";
    $m1data = get_userids_and_values_from_mn($filem1);
    $filem2 = $filePath . $classSelection . "_m2.json";
    $m2data = get_userids_and_values_from_mn($filem2);

    // Array per i dati CSV
    $tableData = [];

    // Inizia la tabella HTML
    $table = '<table style="width: 100%; border-collapse: collapse; text-align: center;" border="1" cellpadding="10">';
    $table .= '<thead><tr>';
    $table .= '<th>STUDENT ID</th><th>MR</th><th>M0</th><th>MPA</th><th>M1</th><th>M1-MPA</th><th>M1-M0</th>';
    $table .= '</tr></thead>';
    $table .= '<tbody>';

    foreach ($userIds as $userId) {
        // Recupera i valori
        $mrValue = isset($useridmr[$userId]) 
            ? '<div style="line-height: 1.5;">K: ' . format_value($useridmr[$userId]['k']) . '<br>J: ' . format_value($useridmr[$userId]['j']) . '</div>'
            : "N/A";

        $m0Value = isset($m0data[$userId]) 
            ? '<div style="line-height: 1.5;">K: ' . format_value($m0data[$userId]['k'] ?? "N/A") . '<br>J: ' . format_value($m0data[$userId]['j'] ?? "N/A") . '<br>C: ' . format_value($m0data[$userId]['c'] ?? "N/A") . '</div>'
            : "N/A";

        $mpaValue = isset($m2data[$userId]) 
            ? '<div style="line-height: 1.5;">K: ' . format_value($m2data[$userId]['k'] ?? "N/A") . '<br>J: ' . format_value($m2data[$userId]['j'] ?? "N/A") . '<br>C: ' . format_value($m2data[$userId]['c'] ?? "N/A") . '</div>'
            : "N/A";

        $m1Value = isset($m1data[$userId]) 
            ? '<div style="line-height: 1.5;">K: ' . format_value($m1data[$userId]['k'] ?? "N/A") . '<br>J: ' . format_value($m1data[$userId]['j'] ?? "N/A") . '<br>C: ' . format_value($m1data[$userId]['c'] ?? "N/A") . '</div>'
            : "N/A";

        $m1MpaDiff = calculate_and_format_differences($m1data[$userId] ?? null, $m2data[$userId] ?? null);
        $m1M0Diff = calculate_and_format_differences($m1data[$userId] ?? null, $m0data[$userId] ?? null);

        // Aggiungi la riga all'array per CSV
        $tableData[] = [
            "STUDENT ID" => $userId,
            "MR" => strip_tags(str_replace('<br>', "\n", $mrValue)),
            "M0" => strip_tags(str_replace('<br>', "\n", $m0Value)),
            "MPA" => strip_tags(str_replace('<br>', "\n", $mpaValue)),
            "M1" => strip_tags(str_replace('<br>', "\n", $m1Value)),
            "M1-MPA" => strip_tags($m1MpaDiff),
            "M1-M0" => strip_tags($m1M0Diff)
        ];

        // Crea la riga HTML
        $table .= "<tr>";
        $table .= "<td>" . htmlspecialchars($userId) . "</td>";
        $table .= "<td>" . $mrValue . "</td>";
        $table .= "<td>" . $m0Value . "</td>";
        $table .= "<td>" . $mpaValue . "</td>";
        $table .= "<td>" . $m1Value . "</td>";
        $table .= "<td>" . $m1MpaDiff . "</td>";
        $table .= "<td>" . $m1M0Diff . "</td>";
        $table .= "</tr>";
    }

    $table .= '</tbody></table>';

    // Salva in CSV
    $outputFilePath = $filePath . $classSelection . "_overview.csv";
    save_class_overview_to_csv($tableData, $outputFilePath);

    return $table;
}


function save_class_overview_to_csv($tableData, $outputFilePath) {
    // Apri il file in modalità scrittura
    $file = fopen($outputFilePath, 'w');
    if ($file === false) {
        throw new Exception("Unable to open file: $outputFilePath");
    }

    // Scrivi l'intestazione della tabella
    $headers = ['STUDENT ID', 'MR', 'M0', 'MPA', 'M1', 'M1-MPA'];
    fputcsv($file, $headers);

    // Scrivi i dati
    foreach ($tableData as $row) {
        fputcsv($file, $row);
    }

    // Chiudi il file
    fclose($file);

}

// Funzione per arrotondare i numeri e gestire N/A
function format_value($value) {
    return is_numeric($value) ? number_format((float)$value, 4) : "N/A";
}

// Funzione per calcolare e formattare le differenze
function calculate_and_format_differences($data1, $data2) {
    if ($data1 === null || $data2 === null) {
        return "N/A";
    }

    $diffs = [];
    foreach (['k', 'j', 'c'] as $key) {
        if (isset($data1[$key], $data2[$key])) {
            $diff = $data1[$key] - $data2[$key];
            $formattedDiff = format_value($diff);
            $coloredDiff = $diff > 0 
                ? "<span style='color: green; font-weight: bold;'>$formattedDiff</span>"
                : "<span style='color: red; font-weight: bold;'>$formattedDiff</span>";
            $diffs[] = strtoupper($key) . ": " . $coloredDiff;
        } else {
            $diffs[] = strtoupper($key) . ": N/A";
        }
    }

    return implode("<br>", $diffs);
}

// Funzione per calcolare la differenza K-J (opzionale)
function calculate_difference_k_and_j($filem1, $filem2, $userId) {
    $m1 = get_value_from_json($filem1, $userId);
    $mpa = get_value_from_json($filem2, $userId);

    if ($m1 !== null && $mpa !== null) {
        $m1K = $m1['k'] ?? 0;
        $mpaK = $mpa['k'] ?? 0;
        return $m1K - $mpaK;
    }

    return "N/A";
}
// Funzione per estrarre K e J formattati
function format_k_and_j($value) {
    if (is_array($value)) {
        $k = isset($value['k']) ? $value['k'] : "N/A";
        $j = isset($value['j']) ? $value['j'] : "N/A";
        return "K: " . htmlspecialchars($k) . "<br>J: " . htmlspecialchars($j);
    }
    return "N/A";
}

// Funzione ausiliaria per estrarre i valori dal file JSON
function get_value_from_json($filePath, $userId) {
    // Leggi il contenuto del file JSON
    if (!file_exists($filePath)) {
        return 'N/A'; // Se il file non esiste, ritorna 'N/A'
    }

    $jsonContent = file_get_contents($filePath);
    $data = json_decode($jsonContent, true);

    // Cerca l'userid nel file JSON
    foreach ($data as $entry) {
        if (isset($entry['userid']) && $entry['userid'] == $userId) {
            return isset($entry['capabilityoverallvalue']) ? $entry['capabilityoverallvalue'] : 'N/A';
        }
    }

    return 'N/A'; // Se l'userId non viene trovato nel file
}

function get_userids_and_values_from_mn($jsonFilePath) {
    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        echo "File not found: " . htmlspecialchars($jsonFilePath);
        return null;
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $data = json_decode($jsonContent, true);

    // Controlla errori di decodifica
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return null;
    }

    // Inizializza l'array per i risultati
    $results = [];

    // Verifica se la chiave "student-models" esiste
    if (isset($data['student-models'])) {
        foreach ($data['student-models'] as $userid => $models) {
            // Assicura che l'userid sia un numero intero
            if (is_numeric($userid)) {
                $userid = intval($userid);

                // Estrai i valori di k, j e c
                $kValue = isset($models['K']['value']) ? $models['K']['value'] : 'N/A';
                $jValue = isset($models['J']['value']) ? $models['J']['value'] : 'N/A';
                $cValue = isset($models['C']['value']) ? $models['C']['value'] : 'N/A';

                // Aggiungi i risultati all'array
                $results[$userid] = [
                    'k' => $kValue,
                    'j' => $jValue,
                    'c' => $cValue
                ];
            }
        }
    }

    return $results;
}


function get_userids_and_values_from_mr($jsonFilePath) {
    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        echo "File not found: " . htmlspecialchars($jsonFilePath);
        return null;
    }

    // Leggi il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $data = json_decode($jsonContent, true);

    // Controlla errori di decodifica
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg();
        return null;
    }

    // Inizializza l'array per raggruppare i dati
    $userData = [];

    // Analizza ogni elemento del JSON
    foreach ($data as $entry) {
        if (
            isset($entry['userid'], $entry['capabilityid'], $entry['domainvalueid'], $entry['capabilityoverallvalue'])
        ) {
            $userid = $entry['userid'];

            // Inizializza la struttura per l'utente se non esiste
            if (!isset($userData[$userid])) {
                $userData[$userid] = [
                    'k' => null, // capabilityid=1 e domainvalueid=1
                    'j' => null  // capabilityid=2 e domainvalueid=1
                ];
            }

            // Caso capabilityid=1 e domainvalueid=1 (k)
            if ($entry['capabilityid'] == "1" && $entry['domainvalueid'] == "1") {
                $userData[$userid]['k'] = floatval($entry['capabilityoverallvalue']);
            }

            // Caso capabilityid=2 e domainvalueid=1 (j)
            if ($entry['capabilityid'] == "2" && $entry['domainvalueid'] == "1") {
                $userData[$userid]['j'] = floatval($entry['capabilityoverallvalue']);
            }
        }
    }

    // Restituisci i dati raggruppati per utente
    return $userData;
}


# DEBUG
function write_log($message, $log_file = 'logfile.log') {
    // Apre il file di log in modalità append (aggiunge alla fine del file)
    $file = fopen($log_file, 'a');
    
    // Verifica se il file è stato aperto correttamente
    if ($file) {
        // Ottiene la data e l'ora attuali
        $timestamp = date('Y-m-d H:i:s');
        
        // Scrive il messaggio nel file di log con il timestamp
        fwrite($file, "[$timestamp] $message\n");
        
        // Chiude il file dopo aver scritto
        fclose($file);
    } else {
        // Se non riesce ad aprire il file, stampa un errore
        echo "Impossibile aprire il file di log.";
    }
}

function check_directories() {
    // Nome della cartella da verificare
    $directory = 'simulatedclass';

    // Verifica se la cartella esiste
    if (!is_dir($directory)) {
        // Se la cartella non esiste, la crea
        mkdir($directory);
    }
}

# Rete Bayesiana
function send_data($filePath, $classSelection) {
    $webservice = new WebServiceBN();

    // Costruisce il percorso completo del file JSON da leggere
    $jsonFilePath = $filePath . $classSelection . "_peerassessment.json";

    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Il file $jsonFilePath non esiste.");
    }

    // Legge il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $sessiondata = json_decode($jsonContent, true);

    // Invia i dati al servizio web
    $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);

    // Costruisce il percorso per salvare la risposta
    $responseFilePath = $filePath . $classSelection;

    // Salva la risposta in due formati
    $output = var_export($studentmodelsjsonresponse, true);
    $output = trim($output, "'");
    file_put_contents($responseFilePath . "_m0.json", $output);
    file_put_contents($responseFilePath, json_encode($studentmodelsjsonresponse, JSON_PRETTY_PRINT));
}

function send_data_for_model($filePath, $classSelection) {
    $webservice = new WebServiceBN();

    // Costruisce il percorso completo del file JSON da leggere
    $jsonFilePath = $filePath . $classSelection . "_peerassessment.json";

    // Controlla se il file esiste
    if (!file_exists($jsonFilePath)) {
        throw new Exception("Il file $jsonFilePath non esiste.");
    }

    // Legge il contenuto del file JSON
    $jsonContent = file_get_contents($jsonFilePath);
    $sessiondata = json_decode($jsonContent, true);

    // Invia i dati al servizio web
    $studentmodelsjsonresponse = $webservice->post_session_data($sessiondata);

    // Trova il prossimo numero disponibile per il file _m#
    $nextModelNumber = get_next_model_number($filePath, $classSelection);

    // Costruisce il nome del file
    $responseFilePath = $filePath . $classSelection . "_m" . $nextModelNumber . ".json";

    // Salva la risposta
    $output = var_export($studentmodelsjsonresponse, true);
    $output = trim($output, "'");
    file_put_contents($responseFilePath, $output);

}

// Trova il prossimo numero disponibile per un file _m#.
function get_next_model_number($filePath, $classSelection) {
    $modelNumber = 1;

    // Scansiona i file nella directory
    $files = scandir($filePath);

    // Cerca i file con il pattern $classSelection_m#
    foreach ($files as $file) {
        if (preg_match('/' . preg_quote($classSelection) . '_m(\d+)\.json$/', $file, $matches)) {
            $number = intval($matches[1]);
            if ($number >= $modelNumber) {
                $modelNumber = $number + 1; // Incrementa al numero successivo
            }
        }
    }

    return $modelNumber;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Class Simulation">
    <meta name="author" content="Pasquale Palombo">
    <title>Class Simulation</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        /* Per la funzionalità di far apparire/sparire la sezione */
        .hidden {
            display: none;
        }
        /* Layout per il footer fisso */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        /* Fa sì che il contenuto principale si espanda */
        main {
            flex: 1; 
        }
        /* Impostazione standard */
        footer {
            position: relative;
        }
    </style>
</head>

<body>
    <?php check_directories(); ?>
    <!-- Hero Section -->
    <header class="bg-primary text-white text-center py-5">
        <div class="container">
            <a href="simulationclass.php" class="text-white text-decoration-none">
                <h1 class="display-4">Class Simulation for Massive Online Open Courses</h1>
            </a>
        </div>
    </header>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-target="class-settings">Class Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-target="peer-assessment">Session settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-target="teacher-settings">Teacher Settings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    

    <!-- Main Content -->
    <main class="container my-5">
    <section id="class-settings" class="mb-5">
        <h2>Class Settings</h2>
        <form action="simulationclass.php" method="POST" enctype="multipart/form-data">
            <!-- Students Number -->
            <div class="mb-3">
                <label for="studentsNumber" class="form-label">Students Number</label>
                <input type="number" class="form-control" id="studentsNumber" name="studentsNumber" value="12" required>
            </div>

            <!-- Distribution -->
            <div class="mb-3">
                <label for="distribution" class="form-label">Distribution</label>
                <select class="form-select" id="distribution" name="distribution" onchange="updateFormFields()">
                    <option value="gaussian">Normal distribution</option>
                    <option value="random">Random distribution</option>
                    <option value="json">From json file</option>
                </select>
            </div>

            <!-- Fields for Normal Distribution -->
            <div id="normal-fields" style="display: none;">
                <label for="median" class="form-label">Median</label>
                <input type="number" class="form-control" id="median" name="median" value="0">
                <label for="stdDeviation" class="form-label">Standard Deviation</label>
                <input type="number" class="form-control" id="standard-deviation" name="standardDeviation" value="0">
                <label for="skewness" class="form-label">Skewness</label>
                <input type="number" class="form-control" id="skewness" name="skewness" value="0">
            </div>

            <!-- Fields for JSON File -->
            <div id="json-fields">
                <label for="fileUpload" class="form-label">Choose a file to upload:</label>
                <input type="file" id="fileUpload" name="fileUpload" class="form-control mb-3">
            </div>

            <!-- Submit Button -->
            <div class="mb-3">
                <button type="submit" class="btn btn-primary" name="class_settings_btn">Create Class</button>
            </div>
        </form>
    </section>

        <section id="peer-assessment" class="mb-5">
            <h2>Session Settings</h2>
            <form action="simulationclass.php" method="POST">
                <!-- Class Selection -->
                <div class="mb-3">
                    <label for="classSelection" class="form-label">Class Selection</label>
                    <select class="form-select" id="classSelection" name="classSelection">
                        <?php generate_class_options(); ?>
                    </select>
                </div>
                <div id="sessionFiles"></div>
                <h2>Peer Assessment Settings</h2>
                <!-- Chose M0 Model -->
                <div class="mb-3">
                    <label for="m0Model" class="form-label">Choose M0 Model</label>
                    <select class="form-select" id="m0Model" name="m0Model">
                        <option value="flat">Flat</option>
                        <option value="random">Random</option>
                    </select>
                </div>

                <!-- Peer Number -->
                <div class="mb-3">
                    <label for="peerNumber" class="form-label">Peer Number</label>
                    <input type="number" class="form-control" id="peerNumber" name="peerNumber" value="3" required>
                </div>
                
                <!-- Randomness -->
                <div class="mb-3">
                    <label class="form-label">Randomness</label>
                    <div class="row">
                        <div class="col">
                            <input type="number" class="form-control" id="randomMin" name="randomMin" value="0" step="0.01" required>
                            <small class="form-text">Minimum</small>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" id="randomMax" name="randomMax" value="0.1" step="0.01" required>
                            <small class="form-text">Maximum</small>
                        </div>
                    </div>
                </div>

                <!-- Process PA Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" name="pas_settings_btn">Create Session</button>
                </div>
            </form>
        </section>

        <section id="teacher-settings" class="mb-5">
            <h2>Teacher Settings</h2>
            <form action="simulationclass.php" method="POST">
                <!-- Session choice -->
                <div class="mb-3">
                    <label for="sessionSelection" class="form-label">Continue on which session?</label>
                    <select class="form-select" id="sessionSelection" name="sessionSelection" onchange="fetchSessionFilePath(this.value)">
                        <?php generate_session_options(); ?>
                    </select>
                    <div id="selectedSession"></div>
                </div>
                
                <!-- Grade On -->
                <div class="mb-3">
                    <label class="form-label">Grade on:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="gradeRandom" name="gradeOption" value="random" checked>
                        <label class="form-check-label" for="gradeRandom">Random</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="gradeSuitable" name="gradeOption" value="most_suitable">
                        <label class="form-check-label" for="gradeSuitable">Most Suitable</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="gradeSubmission" name="gradeOption" value="submission">
                        <label class="form-check-label" for="gradeSubmission">
                            Student:
                            <select class="form-select d-inline-block w-auto ms-2" name="submissionOption">
                                <option value="no_student">Select a session first</option>
                            </select>
                        </label>
                    </div>
                </div>
                <!-- Randomness -->
                <div class="mb-3">
                    <label class="form-label">Randomness</label>
                    <div class="row">
                        <div class="col">
                            <input type="number" class="form-control" id="randomMin" name="randomMin" value="0" step="0.01" required>
                            <small class="form-text">Minimum</small>
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" id="randomMax" name="randomMax" value="0.1" step="0.01" required>
                            <small class="form-text">Maximum</small>
                        </div>
                    </div>
                </div>
                <!-- Grade Button -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" name="teacher_settings_btn">Grade</button>
                </div>
            </form>
            <div id="table-container">
                <!-- Mostra la tabella solo se generata -->
                <?php if (!empty($tableHTML)) echo $tableHTML; ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p>Tesi @<a href="https://github.com/pasqualepalombo/advwork" class="text-white me-2">Github</a></p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript -->
    <script>
        // Funzione per mostrare solo la sezione selezionata
        function showSection(targetId) {
            // Nascondi tutte le sezioni
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('hidden');
            });

            // Mostra la sezione corrispondente
            document.getElementById(targetId).classList.remove('hidden');

            // Aggiorna la classe attiva sulla navbar
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });

            // Aggiungi la classe attiva al link cliccato
            document.querySelector(`.nav-link[data-target="${targetId}"]`).classList.add('active');
        }

        // Imposta l'evento di clic sui link della navbar
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault(); // Evita il comportamento predefinito del link
                const target = this.getAttribute('data-target'); // Ottieni l'id target
                showSection(target); // Mostra la sezione selezionata
            });
        });

        // Mostra la sezione iniziale (class-settings) al caricamento della pagina
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const target = this.getAttribute('data-target');
                document.querySelectorAll('section').forEach(section => {
                    section.classList.add('hidden');
                });
                document.getElementById(target).classList.remove('hidden');
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('class-settings').classList.remove('hidden');
        });
    
        document.addEventListener('DOMContentLoaded', () => {
            showSection('class-settings');
        });

        function updateFormFields() {
            const distribution = document.getElementById("distribution").value;
            const normalFields = document.getElementById("normal-fields");
            const jsonFields = document.getElementById("json-fields");

            // Reset visibility
            normalFields.style.display = "none";
            jsonFields.style.display = "none";

            // Show appropriate fields based on selection
            if (distribution === "gaussian") {
                normalFields.style.display = "block";
            } else if (distribution === "json") {
                jsonFields.style.display = "block";
            }
        }

        // Initialize form visibility on page load
        document.addEventListener("DOMContentLoaded", updateFormFields);

        function fetchSessionFilePath(selectedValue) {
            console.log("Selected value passed to fetchSessionFilePath:", selectedValue);

            if (!selectedValue) {
                document.getElementById("selectedSession").innerHTML = "No session selected.";
                return;
            }

            // Invia il filepath per aggiornare le opzioni
            fetch("simulationclass.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "filepath=" + encodeURIComponent(selectedValue)
            })
            .then(response => response.text())
            .then(data => {
                console.log("Response data from the server:", data);
                // Aggiorna il menu a tendina per gli studenti
                document.querySelector('select[name="submissionOption"]').innerHTML = data;
            })
            .catch(error => {
                console.error("Error fetching student options:", error);
                document.querySelector('select[name="submissionOption"]').innerHTML = '<option value="no_student">An error occurred</option>';
            });
        }

    </script>
</body>

</html>
