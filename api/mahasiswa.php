
<?php
/**
 * Praktikum 5: Endpoint REST API Mahasiswa Berbasis MySQL & PDO
 * File: api/mahasiswa.php
 * Mendukung GET, POST, PUT, DELETE dengan respons JSON.
 */

// Header respons API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Tangani pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Inisialisasi koneksi database
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal terhubung ke database.',
        'detail' => $e->getMessage()
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Routing berdasarkan metode HTTP
try {
    switch ($method) {
        case 'GET':
            handleGet($pdo, $id);
            break;

        case 'POST':
            handlePost($pdo);
            break;

        case 'PUT':
            handlePut($pdo, $id);
            break;

        case 'DELETE':
            handleDelete($pdo, $id);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => "Metode HTTP {$method} tidak diizinkan."
            ]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada database.',
        'detail' => $e->getMessage()
    ]);
}

/**
 * 1. GET: mengambil semua data, mencari data,
 *    atau mengambil data berdasarkan ID.
 */
function handleGet(PDO $pdo, ?int $id): void
{
    if ($id !== null && $id > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([':id' => $id]);
        $mahasiswa = $stmt->fetch();

        if ($mahasiswa) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $mahasiswa
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
            ]);
        }

        return;
    }

    $search = isset($_GET['search'])
        ? trim($_GET['search'])
        : '';

    if ($search !== '') {
        // Kata kunci pencarian untuk NPM, nama, dan program studi
        $keyword = '%' . $search . '%';

        $sql = 'SELECT id, npm, nama, program_studi, angkatan, email, created_at
                FROM mahasiswa
                WHERE npm LIKE :kw1
                   OR nama LIKE :kw2
                   OR program_studi LIKE :kw3
                ORDER BY id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':kw1' => $keyword,
            ':kw2' => $keyword,
            ':kw3' => $keyword
        ]);
    } else {
        // Ambil seluruh data mahasiswa
        $stmt = $pdo->query(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa
             ORDER BY id DESC'
        );
    }

    $result = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'total' => count($result),
        'data' => $result
    ]);
}

/**
 * 2. POST: menambahkan mahasiswa baru.
 */
function handlePost(PDO $pdo): void
{
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // Mendukung pengiriman data melalui form biasa
    if (!is_array($input)) {
        $input = $_POST;
    }

    $npm = trim($input['npm'] ?? '');
    $nama = trim($input['nama'] ?? '');
    $programStudi = trim($input['program_studi'] ?? '');
    $angkatan = (int) ($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        $npm === '' ||
        $nama === '' ||
        $programStudi === '' ||
        $angkatan <= 0 ||
        $email === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seluruh bidang isian wajib diisi.'
        ]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Format email tidak valid.'
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO mahasiswa
            (npm, nama, program_studi, angkatan, email)
         VALUES
            (:npm, :nama, :prodi, :angkatan, :email)'
    );

    $stmt->execute([
        ':npm' => $npm,
        ':nama' => $nama,
        ':prodi' => $programStudi,
        ':angkatan' => $angkatan,
        ':email' => $email
    ]);

    $newId = (int) $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data mahasiswa berhasil ditambahkan.',
        'data' => [
            'id' => $newId,
            'npm' => $npm,
            'nama' => $nama,
            'program_studi' => $programStudi,
            'angkatan' => $angkatan,
            'email' => $email
        ]
    ]);
}

/**
 * 3. PUT: memperbarui data mahasiswa berdasarkan ID.
 */
function handlePut(PDO $pdo, ?int $id): void
{
    if ($id === null || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID mahasiswa wajib disertakan.'
        ]);
        return;
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Body harus berisi JSON yang valid.'
        ]);
        return;
    }

    $nama = trim($input['nama'] ?? '');
    $programStudi = trim($input['program_studi'] ?? '');
    $angkatan = (int) ($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        $nama === '' ||
        $programStudi === '' ||
        $angkatan <= 0 ||
        $email === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Nama, program studi, angkatan, dan email wajib diisi.'
        ]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Format email tidak valid.'
        ]);
        return;
    }

    // Pastikan data yang akan diperbarui memang ada
    $check = $pdo->prepare(
        'SELECT id FROM mahasiswa WHERE id = :id'
    );
    $check->execute([':id' => $id]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE mahasiswa
         SET nama = :nama,
             program_studi = :prodi,
             angkatan = :angkatan,
             email = :email
         WHERE id = :id'
    );

    $stmt->execute([
        ':nama' => $nama,
        ':prodi' => $programStudi,
        ':angkatan' => $angkatan,
        ':email' => $email,
        ':id' => $id
    ]);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data mahasiswa berhasil diperbarui.',
        'id' => $id
    ]);
}

/**
 * 4. DELETE: menghapus mahasiswa berdasarkan ID.
 */
function handleDelete(PDO $pdo, ?int $id): void
{
    if ($id === null || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID mahasiswa wajib disertakan.'
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM mahasiswa WHERE id = :id'
    );

    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
        ]);
        return;
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data mahasiswa berhasil dihapus.',
        'id' => $id
    ]);
}
