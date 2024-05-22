<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Origin: *');
include 'koneksi.php';

function generateRandomFileName($prefix = '', $suffix = '') {
    $datePart = date("YmdHis");
    $randomFileName = $prefix . $datePart . $suffix;
    return $randomFileName;
}

function tambahLaporan($id_user, $laporan_text, $laporan_pdf, $status, $ktp) {
    global $conn;

    $outputfile = "pdf/" . generateRandomFileName(str_replace(' ', '', 'hukum',) . '_', '.pdf');
    $filehandler = fopen($outputfile, 'wb');
    try {
        fwrite($filehandler, base64_decode($laporan_pdf));
    } catch (Exception $e) {
        echo 'Kesalahan fwrite(): ', $e->getMessage(), "\n";
    }
    fclose($filehandler);

    $ktpFile = "pdf/" . generateRandomFileName('ktp_', '.pdf');
    $filehandler = fopen($ktpFile, 'wb');
    try {
        fwrite($filehandler, base64_decode($ktp));
    } catch (Exception $e) {
        echo 'Kesalahan fwrite(): ', $e->getMessage(), "\n";
    }
    fclose($filehandler);

    $sql = "INSERT INTO tb_hukum (id_user, laporan_text, laporan_pdf, status,  ktp) VALUES ('$id_user', '$laporan_text', '$outputfile', '$status', '$ktpFile')";

    if (mysqli_query($conn, $sql)) {
        return true;
    } else {
        return false;
    }
}

function semuaLaporan() {
    global $conn;
    $sql = "SELECT * FROM tb_hukum";
    $result = $conn->query($sql);
    $laporan = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $laporan[] = $row;
        }
    }
    return $laporan;
}

function laporanUser($id) {
    global $conn;
    $sql = "SELECT * FROM tb_hukum WHERE id_user=$id";
    $result = $conn->query($sql);
    $laporan = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $laporan[] = $row;
        }
    }
    return $laporan;
}

function ubahLaporan($id, $laporan_text, $laporan_pdf, $status,  $ktp) {
    global $conn;

    // Cek apakah file PDF baru diunggah
    if (!empty($laporan_pdf)) {
        $outputfile = "pdf/" . generateRandomFileName(str_replace(' ', '', 'hukum') . '_', '.pdf');
        $filehandler = fopen($outputfile, 'wb');
        fwrite($filehandler, base64_decode($laporan_pdf));
        fclose($filehandler);
        $laporan_pdf = $outputfile;
    }

    if (!empty($ktp)) {
        $ktpFile = "pdf/" . generateRandomFileName('ktp_', '.pdf');
        $filehandler = fopen($ktpFile, 'wb');
        fwrite($filehandler, base64_decode($ktp));
        fclose($filehandler);
        $ktp = $ktpFile;
    }

    $sql = "UPDATE tb_hukum SET laporan_text='$laporan_text', status='$status'";

    if (!empty($laporan_pdf)) {
        $sql .= ", laporan_pdf='$laporan_pdf'";
    }

    if (!empty($ktp)) {
        $sql .= ", ktp='$ktp'";
    }

    $sql .= " WHERE id_laporan=$id";

    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        return false;
    }
}

function hapusLaporan($id) {
    global $conn;

    // Mendapatkan nama file PDF dari database
    $sql = "SELECT laporan_pdf, ktp FROM tb_hukum WHERE id_laporan = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_path = $row['laporan_pdf'];
        $ktp_path = $row['ktp'];

        // Menghapus file dari sistem file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        if (file_exists($ktp_path)) {
            unlink($ktp_path);
        }

        // Menghapus data laporan dari database
        $sql_delete = "DELETE FROM tb_hukum WHERE id_laporan = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        if (isset($_GET["id_laporan"])) {
            $id = $_GET["id_laporan"];
            if (hapusLaporan($id)) {
                echo json_encode(array("sukses" => true, "status" => 200, "pesan" => "Data berhasil Dihapus"));
            } else {
                echo json_encode(array("sukses" => false, "status" => 500, "pesan" => "Gagal Menghapus Data"));
            }
        } else if (isset($_GET['id'])) {
            $id = $_GET["id"];
            if (laporanUser($id)) {
                echo json_encode(array("sukses" => true, "status" => 200, "pesan" => "Berhasil Mendapatkan Semua Laporan", "data" => laporanUser($id)));
            } else {
                echo json_encode(array("sukses" => false, "status" => 500, "pesan" => "Data Tidak Ditemukan"));
            }
        } else {
            echo json_encode(array("sukses" => true, "status" => 200, "pesan" => "Berhasil Mendapatkan Semua Laporan", "data" => semuaLaporan()));
        }
        break;
    case 'POST':
        $id_user = $_POST["id_user"];
        $laporan_text = $_POST["laporan_text"];
        $laporan_pdf = $_POST["laporan_pdf"];
        $status = $_POST["status"];
        $ktp = $_POST["ktp"];
        if (isset($_POST["id_laporan"])) {
            $id = $_POST["id_laporan"];
            if (ubahLaporan($id, $laporan_text, $laporan_pdf, $status, $ktp)) {
                echo json_encode(array("sukses" => true, "status" => 200, "pesan" => "Data berhasil Diubah"));
            } else {
                echo json_encode(array("sukses" => false, "status" => 500, "pesan" => "Gagal Mengubah Data"));
            }
        } else {
            if (tambahLaporan($id_user, $laporan_text, $laporan_pdf, $status, $ktp)) {
                echo json_encode(array("sukses" => true, "status" => 200, "pesan" => "Data berhasil Ditambahkan"));
            } else {
                echo json_encode(array("sukses" => false, "status" => 500, "pesan" => "Gagal Menambahkan Data"));
            }
        }
        break;
    default:
        echo json_encode(array("sukses" => true, "status" => 400, "pesan" => "Method Tidak di kenal"));
        break;
}

$conn->close();
?>
