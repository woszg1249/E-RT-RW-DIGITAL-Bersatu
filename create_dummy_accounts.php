<?php
/*
  Skrip untuk membuat 100 akun dummy ke tabel `users`.
  Menyesuaikan struktur pada `register.php`: (nama, username, email, password, alamat, kelamin, no_hp, role)
  Password disimpan dalam bentuk plain text agar kompatibel dengan mekanisme login saat ini.
  Jalankan skrip ini lewat browser atau CLI: php create_dummy_accounts.php
*/

include 'Koneksi.php';

function sanitize($k, $s){
    return mysqli_real_escape_string($k, $s);
}

$first_male = ["Agus","Budi","Joko","Andi","Rudi","Eko","Hendra","Dedi","Fajar","Rian","Satria","Yudi","Iwan","Teguh","Bayu","Rizky","Ahmad","Aris","Darma","Wawan"];
$first_female = ["Siti","Ayu","Rina","Dewi","Nina","Sari","Wati","Indah","Maya","Yuni","Lia","Fitri","Rizka","Yulia","Ririn","Nadia","Putri","Ika","Wulan","Ria"];
$lastnames = ["Santoso","Pratama","Wijaya","Sukma","Setiawan","Halim","Saputra","Kusuma","Hidayat","Purnama","Saputri","Ramadhani","Utami","Herman","Wibowo","Nasution","Hartono","Mahendra","Kusnadi","Siregar"];
$streets = ["Jalan"];

$total = 100;
$inserted = 0;

// alternate genders to ensure mix
for ($i=1; $i<=$total; $i++){
    $isMale = ($i % 2 == 1); // odd -> male
    if ($isMale){
        $first = $first_male[array_rand($first_male)];
    } else {
        $first = $first_female[array_rand($first_female)];
    }
    $last = $lastnames[array_rand($lastnames)];
    $street = $streets[array_rand($streets)];

    $nama = $first . ' ' . $last;
    $username_base = strtolower(preg_replace('/[^a-z]/', '', $first)) . '.' . strtolower(preg_replace('/[^a-z]/', '', $last));
    // ensure username uniqueness by appending index
    $username = $username_base . $i;

    // email uses street name to reflect alamat
    $street_safe = strtolower(preg_replace('/[^a-z0-9]/', '', $street));
    $email = $username_base . $i . '@' . $street_safe . '.test';

    $alamat = 'Jl. ' . $street . ' No. ' . rand(1,200) . ', Bekasi Timur, Kota Bekasi, Jawa Barat, Republik Indonesia';
    $kelamin = $isMale ? 'Laki-laki' : 'Perempuan';
    $no_hp = '08' . rand(100000000, 999999999);
    $password = 'Password123';
    $role = 'Warga';

    $n = sanitize($koneksi, $nama);
    $u = sanitize($koneksi, $username);
    $e = sanitize($koneksi, $email);
    $p = sanitize($koneksi, $password);
    $a = sanitize($koneksi, $alamat);
    $k = sanitize($koneksi, $kelamin);
    $hp = sanitize($koneksi, $no_hp);
    $r = sanitize($koneksi, $role);

    $sql = "INSERT INTO users (nama, username, email, password, alamat, kelamin, no_hp, role) VALUES ('$n', '$u', '$e', '$p', '$a', '$k', '$hp', '$r')";

    $res = mysqli_query($koneksi, $sql);
    if ($res) $inserted++;
}

echo "Selesai. Total berhasil dimasukkan: $inserted / $total\n";
echo "Password default semua akun: Password123\n";

// optional: tampilkan 10 contoh akun
$result = mysqli_query($koneksi, "SELECT nama, username, email, alamat, kelamin, no_hp FROM users ORDER BY id DESC LIMIT 10");
if ($result && mysqli_num_rows($result) > 0){
    echo "\nContoh 10 akun terakhir:\n";
    while($row = mysqli_fetch_assoc($result)){
        echo "- " . $row['nama'] . " | " . $row['username'] . " | " . $row['email'] . " | " . $row['alamat'] . " | " . $row['kelamin'] . " | " . $row['no_hp'] . "\n";
    }
}

?>
