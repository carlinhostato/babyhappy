<?php
// test_paths.php - FERRAMENTA DE DIAGNÓSTICO DE CAMINHOS
// 🚨 AVISO DE SEGURANÇA: NUNCA DEIXE ESTE FICHEIRO EM AMBIENTE DE PRODUÇÃO.

echo "<h2>🚨 FERRAMENTA DE DIAGNÓSTICO DE CAMINHOS (babyhappy_v1)</h2>";
echo "<p style='color:red;'>Este script expõe a estrutura de pastas. Deve ser APAGADO IMEDIATAMENTE após uso.</p>";
echo "<pre style='background:#f4f4f4; padding: 15px; border: 1px solid #ddd;'>";

$current_dir = __DIR__;
echo "Diretório atual (onde este ficheiro está): " . htmlspecialchars($current_dir) . "\n\n";

// --- DECLARAÇÃO DO ARRAY (Linhas 14-17 do seu log) ---
// Note que as vírgulas e as aspas estão corretas aqui.
$possible_paths = [
    '1. Relativo: 1 nível acima' => '../config/database.php',
    '2. Relativo: 2 níveis acima' => '../../config/database.php',
    '3. Relativo: 3 níveis acima' => '../../../config/database.php', 
    '4. Relativo: 4 níveis acima' => '../../../../config/database.php'
];
// ----------------------------------------------------


echo "--- RESULTADOS DO TESTE DE CAMINHOS ---\n";
foreach ($possible_paths as $description => $path) {
    // Constrói o caminho absoluto para teste, relativo ao ficheiro atual
    $test_path = $current_dir . '/' . $path;
    
    // realpath resolve todos os ../ e retorna o caminho absoluto canónico se o ficheiro existir.
    $full_path = realpath($test_path);
    
    // Verifica se o ficheiro existe e se o realpath conseguiu resolver o caminho
    if ($full_path && file_exists($full_path) && strpos($full_path, 'config/database.php') !== false) {
        echo "✅ SUCESSO! $description: O caminho a usar é: " . htmlspecialchars($path) . "\n";
        echo "   (Caminho Resolvido: " . htmlspecialchars($full_path) . ")\n";
    } else {
        echo "❌ FALHA! $description: " . htmlspecialchars($path) . "\n";
    }
}

echo "\n--- ESTRUTURA DE PASTAS (Visualização a partir do diretório pai) ---\n";
echo "Use esta estrutura para contar quantos '../' são necessários.\n";

// Vai um nível acima para mostrar o contexto (assumindo que 'config' está no raiz do projeto)
$parent_dir = dirname($current_dir);

function scanDirRecursive($dir, $level = 0) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        
        // Limita a profundidade para evitar scan lento e focar em /config
        if ($level < 2) { 
            echo str_repeat('  ', $level) . (is_dir($path) ? '📁 ' : '📄 ') . htmlspecialchars($item) . "\n";
            if (is_dir($path)) {
                scanDirRecursive($path, $level + 1);
            }
        } else if (is_dir($path)) {
            // Se for uma pasta, mostra (...) para indicar que há mais conteúdo
            echo str_repeat('  ', $level) . '📁 ' . htmlspecialchars($item) . " (...)\n";
        }
    }
}

// Começa a analisar 1 nível acima do diretório atual (que é tipicamente a pasta /api/)
scanDirRecursive($parent_dir);

echo "</pre>";
?>