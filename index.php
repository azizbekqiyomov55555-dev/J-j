<?php
// ==========================================
// 1. SOZLAMALAR (O'zingiznikiga o'zgartiring)
// ==========================================
$db_host = 'localhost';
$db_name = 'test_baza';   // Xostingdagi ma'lumotlar bazasi nomi (avval xostingdan shu bazani yarating)
$db_user = 'root';        // Baza logini
$db_pass = '';            // Baza paroli

$kassa_id = 47;
$api_secret_key = 'ODZlZjQ2YjY4NDViZDdjMDZiODE';

// ==========================================
// 2. BAZAGA ULANISH VA JADVALLARNI YARATISH
// ==========================================
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Xatolik: Ma'lumotlar bazasiga ulanib bo'lmadi! <br> Avval Xostingizdan '$db_name' nomli baza yarating.</h2>");
}

// Jadvallar yo'q bo'lsa, avtomatik yaratish
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    balance INT DEFAULT 0
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    provider VARCHAR(20),
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Sayt ishlashini sinash uchun 1 ta test foydalanuvchi qo'shamiz (Sizning profilingiz)
$stmt = $pdo->query("SELECT id FROM users WHERE id = 1");
if ($stmt->rowCount() == 0) {
    $pdo->exec("INSERT INTO users (id, username, balance) VALUES (1, 'Azizbek Kripto', 0)");
}

// ==========================================
// 3. WEBHOOK (Checkout.uz dan keladigan javob)
// ==========================================
// Bu qism faqat url da ?action=webhook bo'lsagina ishlaydi
if (isset($_GET['action']) && $_GET['action'] === 'webhook') {
    header('Content-Type: application/json');
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Agar pul muvaffaqiyatli yechilgan bo'lsa (status = paid)
    if ($data && isset($data['status']) && $data['status'] === 'paid') {
        $transaction_id = $data['transaction_id']; // To'lov ID si
        $amount = $data['amount'];                 // Qancha to'langani
        
        // Bazadan shu to'lovni qidirish
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$transaction_id]);
        $txn = $stmt->fetch();

        if ($txn) {
            // To'lovni success (muvaffaqiyatli) deb belgilaymiz
            $pdo->prepare("UPDATE transactions SET status = 'success' WHERE id = ?")->execute([$transaction_id]);
            // Foydalanuvchining HAQIQIY BALANSIGA pul qo'shamiz
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $txn['user_id']]);
            
            echo json_encode(['status' => 'success', 'message' => 'Pul tushdi']);
            exit;
        }
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Xatolik']);
    exit;
}

// ==========================================
// 4. TO'LOVGA YO'NALTIRISH (Tugma bosilganda)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && isset($_POST['provider'])) {
    $user_id = 1; // Hozircha sinov uchun 1-foydalanuvchi qilingan
    $amount = (int)$_POST['amount'];
    $provider = $_POST['provider']; // payme yoki click

    if ($amount >= 1000) { // Eng kam to'lov 1000 so'm
        // Bazaga kutilyapti (pending) holatida saqlaymiz
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, provider, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $amount, $provider]);
        $transaction_id = $pdo->lastInsertId();

        // Qaytish havolasi (To'lovdan so'ng saytga qaytishi uchun)
        $return_url = urlencode("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
        
        // Checkout.uz ning API silakasi yasaymiz (Hujjatdagi standart API formatiga ko'ra)
        $payment_url = "https://checkout.uz/pay?kassa_id={$kassa_id}&amount={$amount}&account={$user_id}&transaction_id={$transaction_id}&provider={$provider}&return_url={$return_url}";

        // Checkout sahifasiga o'tkazib yuboramiz
        header("Location: " . $payment_url);
        exit;
    }
}

// ==========================================
// 5. SAYTNING KO'RINISHI (HTML / UI)
// ==========================================
// Ekranda ko'rsatish uchun foydalanuvchini bazadan olamiz
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = 1");
$stmt->execute();
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balansni To'ldirish</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#0f111a] text-white font-sans antialiased min-h-screen flex items-center justify-center p-4">

    <div class="bg-[#1a1d2d] p-8 rounded-3xl shadow-2xl shadow-indigo-500/10 w-full max-w-md border border-gray-800 relative overflow-hidden">
        
        <!-- Orqa fondagi chiroyli effekt -->
        <div class="absolute top-[-50px] right-[-50px] w-32 h-32 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute bottom-[-50px] left-[-50px] w-32 h-32 bg-blue-500 rounded-full blur-3xl opacity-20"></div>

        <!-- Profil Qismi -->
        <div class="relative z-10 text-center mb-8">
            <div class="w-24 h-24 bg-gradient-to-tr from-indigo-500 to-blue-500 rounded-full mx-auto mb-4 flex items-center justify-center text-4xl font-bold shadow-lg shadow-blue-500/40 border-4 border-[#1a1d2d]">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-wider"><?= htmlspecialchars($user['username']) ?></h2>
            <div class="mt-4 bg-[#0f111a] py-3 px-6 rounded-2xl inline-block border border-gray-800">
                <p class="text-gray-400 text-sm mb-1">Joriy balans</p>
                <div class="text-3xl font-extrabold text-green-400">
                    <?= number_format($user['balance'], 0, '', ' ') ?> <span class="text-xl">UZS</span>
                </div>
            </div>
        </div>

        <div class="h-px w-full bg-gradient-to-r from-transparent via-gray-700 to-transparent mb-8"></div>

        <!-- To'lov Formasi -->
        <form action="" method="POST" class="relative z-10">
            <label class="block text-sm font-semibold text-gray-400 mb-2 ml-1">Qancha pul tushirasiz?</label>
            <div class="relative mb-6">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="text-gray-500 font-bold">UZS</span>
                </div>
                <input type="number" name="amount" min="1000" placeholder="10 000" required
                    class="w-full pl-14 pr-4 py-4 bg-[#0f111a] border border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-white placeholder-gray-600 text-lg transition-all shadow-inner">
            </div>

            <p class="block text-sm font-semibold text-gray-400 mb-3 ml-1">To'lov tizimini tanlang:</p>
            <div class="grid grid-cols-2 gap-4">
                
                <!-- Payme Tugmasi -->
                <button type="submit" name="provider" value="payme" 
                    class="group relative flex flex-col items-center justify-center bg-[#0f111a] border border-gray-700 rounded-2xl p-4 hover:border-cyan-400 hover:shadow-[0_0_15px_rgba(56,189,248,0.2)] transition-all duration-300">
                    <div class="h-10 w-full flex items-center justify-center mb-2">
                        <!-- Payme Logotipi -->
                        <img src="https://cdn.payme.uz/logo/payme_color.svg" alt="Payme" class="max-h-full">
                    </div>
                    <span class="text-sm font-medium text-gray-300 group-hover:text-cyan-400 transition-colors">Payme orqali</span>
                </button>

                <!-- Click Tugmasi -->
                <button type="submit" name="provider" value="click" 
                    class="group relative flex flex-col items-center justify-center bg-[#0f111a] border border-gray-700 rounded-2xl p-4 hover:border-blue-500 hover:shadow-[0_0_15px_rgba(59,130,246,0.2)] transition-all duration-300">
                    <div class="h-10 w-full flex items-center justify-center mb-2">
                        <!-- Click Logotipi -->
                        <img src="https://click.uz/click/images/logo.png" alt="Click" class="max-h-full opacity-90">
                    </div>
                    <span class="text-sm font-medium text-gray-300 group-hover:text-blue-500 transition-colors">Click orqali</span>
                </button>

            </div>
        </form>
        
        <p class="text-center text-xs text-gray-600 mt-6">
            <i class="fa-solid fa-lock mr-1"></i> Barcha to'lovlar himoyalangan
        </p>

    </div>

</body>
</html>
