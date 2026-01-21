# JotForm API Integration - Cara Penggunaan

## Setup

### 1. Update .env File

Buka file `.env` dan tambahkan API key JotForm Anda:

```env
JOTFORM_API_KEY=your_jotform_api_key_here
JOTFORM_FORM_ID=253512987656470
```

**Cara mendapatkan API Key:**
1. Login ke https://www.jotform.com
2. Pergi ke Settings → API
3. Copy API Key Anda

### 2. Run Migration

```bash
php artisan migrate
```

> **Catatan:** Pastikan PHP versi 8.3+ terinstall. Jika error, upgrade PHP Anda.

### 3. Mapping Field Form

Buka `app/Services/JotFormService.php` dan sesuaikan field ID di method `formatSubmissionData()`:

```php
public function formatSubmissionData(array $submission): array
{
    $answers = $submission['answers'] ?? [];

    return [
        'nama_lengkap' => $answers[4]['answer'] ?? null, // Ganti angka 4 dengan field ID yang sesuai
        'email' => $answers[5]['answer'] ?? null,        // Ganti angka 5 dengan field ID yang sesuai
        'nama_sppg' => $answers[6]['answer'] ?? null,    // Ganti angka 6 dengan field ID yang sesuai
        'alamat_sppg' => $answers[7]['answer'] ?? null,  // Ganti angka 7 dengan field ID yang sesuai
        // ... dll
    ];
}
```

**Cara mengetahui Field ID:**
1. Gunakan endpoint: `GET /jotform/form-details`
2. Lihat response JSON untuk mengetahui field ID yang sesuai

---

## API Endpoints

### 1. Sync Submissions (POST)

Sync semua submissions dari JotForm ke database lokal.

**Endpoint:** `POST /jotform/sync`

**Example:**
```bash
curl -X POST http://localhost:8000/jotform/sync \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

**With custom form ID:**
```bash
curl -X POST http://localhost:8000/jotform/sync \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"form_id":"253512987656470"}'
```

**Response:**
```json
{
  "success": true,
  "message": "Sync completed",
  "data": {
    "total_submissions": 50,
    "synced": 30,
    "updated": 20,
    "errors": 0,
    "error_details": []
  }
}
```

### 2. Get All Submissions (GET)

Ambil semua submissions yang sudah disync ke database.

**Endpoint:** `GET /jotform/submissions`

**Query Parameters:**
- `per_page`: Jumlah data per halaman (default: 15)
- `page`: Halaman ke berapa
- `status`: Filter by status (ACTIVE, etc)
- `search`: Search by nama, email, atau nama_sppg

**Examples:**
```bash
# Get all submissions
curl http://localhost:8000/jotform/submissions

# With pagination
curl http://localhost:8000/jotform/submissions?per_page=50&page=1

# Filter by status
curl http://localhost:8000/jotform/submissions?status=ACTIVE

# Search
curl http://localhost:8000/jotform/submissions?search=ahmad
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "uuid",
        "submission_id": "submission_id_from_jotform",
        "form_id": "253512987656470",
        "nama_lengkap": "Ahmad Dahlan",
        "email": "ahmad@example.com",
        "nama_sppg": "SPPG Example",
        "alamat_sppg": "Jl. Contoh No. 123",
        "status_submit": "ACTIVE",
        "synced_at": "2025-12-28T10:00:00.000000Z",
        "created_at_jotform": "2025-12-27T15:30:00.000000Z"
      }
    ],
    "per_page": 15,
    "total": 100
  }
}
```

### 3. Get Form Details (GET)

Ambil detail form dari JotForm API (termasuk field ID mapping).

**Endpoint:** `GET /jotform/form-details`

**Example:**
```bash
curl http://localhost:8000/jotform/form-details
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "253512987656470",
    "title": "Nama Form Anda",
    "fields": [
      {
        "id": "4",
        "name": "nama_lengkap",
        "type": "control_textbox"
      }
    ]
  }
}
```

### 4. Get Statistics (GET)

Ambil statistik sync submissions.

**Endpoint:** `GET /jotform/stats`

**Example:**
```bash
curl http://localhost:8000/jotform/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "active": 140,
    "today": 5,
    "this_week": 25,
    "this_month": 100
  }
}
```

### 5. Web Sync Endpoint (GET)

Untuk sync dari browser (redirect response, bukan JSON).

**Endpoint:** `GET /jotform/sync`

**Example:**
```
http://localhost:8000/jotform/sync
```

---

## Contoh Implementasi di Frontend

### Menggunakan Fetch (JavaScript)

```javascript
// Sync submissions
async function syncSubmissions() {
  try {
    const response = await fetch('/jotform/sync', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    const data = await response.json();
    console.log(data);
  } catch (error) {
    console.error('Error:', error);
  }
}

// Get submissions
async function getSubmissions() {
  try {
    const response = await fetch('/jotform/submissions?per_page=50');
    const data = await response.json();
    console.log(data.data);
  } catch (error) {
    console.error('Error:', error);
  }
}

// Search submissions
async function searchSubmissions(keyword) {
  try {
    const response = await fetch(`/jotform/submissions?search=${keyword}`);
    const data = await response.json();
    console.log(data.data);
  } catch (error) {
    console.error('Error:', error);
  }
}
```

### Menggunakan Livewire (Laravel)

```php
<?php

namespace App\Http\Livewire;

use App\Models\JotformSync;
use Livewire\Component;

class JotformSubmissions extends Component
{
    public $search = '';
    public $status = '';

    public function sync()
    {
        $service = new \App\Services\JotFormService();
        $submissions = $service->getSubmissions(config('services.jotform.form_id'));

        foreach ($submissions as $submission) {
            $data = $service->formatSubmissionData($submission);

            JotformSync::updateOrCreate(
                ['submission_id' => $submission['id']],
                $data
            );
        }

        session()->flash('success', 'Sync completed!');
    }

    public function getSubmissionsProperty()
    {
        return JotformSync::query()
            ->when($this->search, fn($q) => $q
                ->where('nama_lengkap', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->when($this->status, fn($q) => $q
                ->where('status_submit', $this->status)
            )
            ->latest('synced_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.jotform-submissions');
    }
}
```

---

## Troubleshooting

### Error: PHP Version Required

Laravel 11 membutuhkan PHP 8.3+. Upgrade PHP Anda:

```bash
# Windows (Laragon)
# Download PHP 8.3+ dan update Laragon configuration
```

### Error: API Key Invalid

Pastikan API Key sudah benar di `.env` file:

```bash
php artisan config:clear
php artisan cache:clear
```

### Field ID Mapping Salah

Gunakan endpoint `/jotform/form-details` untuk melihat field ID yang benar dari form Anda.

---

## File yang Dibuat/Diupdate

1. **Service:** `app/Services/JotFormService.php`
2. **Controller:** `app/Http/Controllers/JotFormController.php`
3. **Model:** `app/Models/JotformSync.php` (updated)
4. **Migration:** `database/migrations/2025_12_28_000001_add_submission_fields_to_jotform_syncs_table.php`
5. **Config:** `config/services.php` (updated)
6. **Routes:** `routes/web.php` (updated)
7. **Environment:** `.env` (updated)

---

## Dokumentasi Resmi JotForm API

https://api.jotform.com/docs
