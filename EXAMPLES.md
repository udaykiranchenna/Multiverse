# Examples

These examples use Multiverse to run trusted Python workers from Laravel. Python is the package's only supported runtime. See the [README](README.md) for the project direction and setup instructions.

## Table of Contents

1. [Basic Examples](#basic-examples)
2. [Image Processing](#image-processing)
3. [Web Scraping](#web-scraping)
4. [Data Analysis](#data-analysis)
5. [Machine Learning](#machine-learning)
6. [Integration Examples](#integration-examples)

---

## Basic Examples

### Example 1: Echo Worker

**Worker:** `multiverse/python/echo/main.py`

```python
import sys
import json

def main():
    data = json.loads(sys.stdin.read())

    result = {
        "status": "success",
        "message": f"Received: {data.get('message', 'No message')}",
        "data": data
    }

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

**Laravel Usage:**

```php
use MadeItEasyTools\Multiverse\Facades\Multiverse;

$result = Multiverse::run('echo', [
    'message' => 'Hello from Laravel!'
]);

echo $result['message']; // "Received: Hello from Laravel!"
```

---

### Example 2: Calculator Worker

**Worker:** `multiverse/python/calculator/main.py`

```python
import sys
import json

def main():
    data = json.loads(sys.stdin.read())

    try:
        a = float(data.get('a', 0))
        b = float(data.get('b', 0))
        operation = data.get('operation', 'add')

        operations = {
            'add': a + b,
            'subtract': a - b,
            'multiply': a * b,
            'divide': a / b if b != 0 else None
        }

        result_value = operations.get(operation)

        if result_value is None:
            raise ValueError("Invalid operation or division by zero")

        result = {
            "status": "success",
            "data": {
                "result": result_value,
                "operation": f"{a} {operation} {b} = {result_value}"
            }
        }
    except Exception as e:
        result = {
            "status": "error",
            "message": str(e)
        }

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

**Laravel Usage:**

```php
$result = Multiverse::run('calculator', [
    'a' => 10,
    'b' => 5,
    'operation' => 'multiply'
]);

echo $result['data']['result']; // 50
```

---

## Image Processing

### Example 3: Image Resizer

**Requirements:** Add to `multiverse/python/requirements.txt`

```txt
Pillow
requests
```

**Worker:** `multiverse/python/image_resizer/main.py`

```python
import sys
import json
import os
from PIL import Image
import requests
from io import BytesIO

def main():
    data = json.loads(sys.stdin.read())

    try:
        image_url = data.get('image_url')
        width = int(data.get('width', 800))
        height = int(data.get('height', 600))

        # Download image
        if image_url.startswith(('http://', 'https://')):
            response = requests.get(image_url)
            img = Image.open(BytesIO(response.content))
        else:
            img = Image.open(image_url)

        # Resize
        img = img.resize((width, height), Image.Resampling.LANCZOS)

        # Save
        output_dir = os.path.join(os.path.dirname(__file__), 'output')
        os.makedirs(output_dir, exist_ok=True)
        output_path = os.path.join(output_dir, 'resized.jpg')
        img.save(output_path, quality=95)

        result = {
            "status": "success",
            "data": {
                "output_path": output_path,
                "size": f"{width}x{height}"
            }
        }
    except Exception as e:
        result = {"status": "error", "message": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

**Laravel Controller:**

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function resize(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image',
            'width' => 'required|integer|min:1|max:4000',
            'height' => 'required|integer|min:1|max:4000',
        ]);

        // Store uploaded image
        $path = $request->file('image')->store('temp');
        $fullPath = Storage::path($path);

        // Resize using Python worker
        $result = Multiverse::run('image_resizer', [
            'image_url' => $fullPath,
            'width' => $validated['width'],
            'height' => $validated['height'],
        ]);

        if ($result['status'] === 'success') {
            return response()->download($result['data']['output_path']);
        }

        return back()->withErrors(['error' => $result['message']]);
    }
}
```

---

### Example 4: Thumbnail Generator

**Worker:** `multiverse/python/thumbnail/main.py`

```python
import sys
import json
import os
from PIL import Image

def main():
    data = json.loads(sys.stdin.read())

    try:
        image_path = data.get('image_path')
        sizes = data.get('sizes', [
            {'name': 'small', 'width': 150, 'height': 150},
            {'name': 'medium', 'width': 300, 'height': 300},
            {'name': 'large', 'width': 600, 'height': 600},
        ])

        img = Image.open(image_path)
        output_dir = os.path.join(os.path.dirname(__file__), 'output')
        os.makedirs(output_dir, exist_ok=True)

        thumbnails = []

        for size in sizes:
            thumb = img.copy()
            thumb.thumbnail((size['width'], size['height']), Image.Resampling.LANCZOS)

            output_path = os.path.join(output_dir, f"thumb_{size['name']}.jpg")
            thumb.save(output_path, quality=85)

            thumbnails.append({
                'name': size['name'],
                'path': output_path,
                'size': f"{thumb.width}x{thumb.height}"
            })

        result = {
            "status": "success",
            "data": {"thumbnails": thumbnails}
        }
    except Exception as e:
        result = {"status": "error", "message": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

---

## Web Scraping

### Example 5: Website Scraper

**Requirements:**

```txt
requests
beautifulsoup4
```

**Worker:** `multiverse/python/scraper/main.py`

```python
import sys
import json
import requests
from bs4 import BeautifulSoup

def main():
    data = json.loads(sys.stdin.read())

    try:
        url = data.get('url')
        selector = data.get('selector', 'h1')

        response = requests.get(url, headers={
            'User-Agent': 'Mozilla/5.0 (compatible; MultiverseBot/1.0)'
        })
        response.raise_for_status()

        soup = BeautifulSoup(response.content, 'html.parser')
        elements = soup.select(selector)

        results = [elem.get_text(strip=True) for elem in elements]

        result = {
            "status": "success",
            "data": {
                "url": url,
                "selector": selector,
                "results": results,
                "count": len(results)
            }
        }
    except Exception as e:
        result = {"status": "error", "message": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

**Laravel Job:**

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use App\Models\ScrapedData;

class ScrapeWebsiteJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public string $selector
    ) {}

    public function handle(): void
    {
        $result = Multiverse::run('scraper', [
            'url' => $this->url,
            'selector' => $this->selector,
        ]);

        if ($result['status'] === 'success') {
            ScrapedData::create([
                'url' => $this->url,
                'data' => $result['data']['results'],
                'scraped_at' => now(),
            ]);
        }
    }
}
```

---

## Data Analysis

### Example 6: CSV Analyzer

**Requirements:**

```txt
pandas
numpy
```

**Worker:** `multiverse/python/csv_analyzer/main.py`

```python
import sys
import json
import pandas as pd
import numpy as np

def main():
    data = json.loads(sys.stdin.read())

    try:
        csv_path = data.get('csv_path')

        df = pd.read_csv(csv_path)

        analysis = {
            "rows": len(df),
            "columns": len(df.columns),
            "column_names": df.columns.tolist(),
            "summary": df.describe().to_dict(),
            "missing_values": df.isnull().sum().to_dict(),
            "dtypes": df.dtypes.astype(str).to_dict()
        }

        result = {
            "status": "success",
            "data": analysis
        }
    except Exception as e:
        result = {"status": "error", "message": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

---

## Machine Learning

### Example 7: Sentiment Analysis

**Requirements:**

```txt
transformers
torch
```

**Worker:** `multiverse/python/sentiment/main.py`

```python
import sys
import json
from transformers import pipeline

# Load model once (cached after first run)
sentiment_pipeline = pipeline("sentiment-analysis")

def main():
    data = json.loads(sys.stdin.read())

    try:
        text = data.get('text', '')

        result_data = sentiment_pipeline(text)[0]

        result = {
            "status": "success",
            "data": {
                "text": text,
                "sentiment": result_data['label'],
                "confidence": result_data['score']
            }
        }
    except Exception as e:
        result = {"status": "error", "message": str(e)}

    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

**Laravel API:**

```php
Route::post('/api/sentiment', function (Request $request) {
    $validated = $request->validate([
        'text' => 'required|string|max:1000'
    ]);

    $result = Multiverse::run('sentiment', [
        'text' => $validated['text']
    ]);

    return response()->json($result);
});
```

---

## Integration Examples

### Example 8: User Registration with Face Verification

```php
namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'photo' => 'required|image',
        ]);

        // Store photo temporarily
        $photoPath = $request->file('photo')->store('temp');
        $fullPath = storage_path('app/' . $photoPath);

        // Verify face exists in photo
        $faceCheck = Multiverse::run('face_detector', [
            'image_path' => $fullPath
        ]);

        if ($faceCheck['status'] !== 'success' || $faceCheck['data']['faces_count'] !== 1) {
            return back()->withErrors([
                'photo' => 'Photo must contain exactly one face'
            ]);
        }

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'photo_path' => $photoPath,
        ]);

        auth()->login($user);

        return redirect('/dashboard');
    }
}
```

---

### Example 9: Automated Report Generation

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use App\Models\Sale;

class GenerateMonthlyReport extends Command
{
    protected $signature = 'report:monthly';
    protected $description = 'Generate monthly sales report with charts';

    public function handle()
    {
        $sales = Sale::whereMonth('created_at', now()->month)
            ->get(['date', 'amount', 'product'])
            ->toArray();

        // Generate charts using Python (matplotlib)
        $result = Multiverse::run('chart_generator', [
            'data' => $sales,
            'chart_type' => 'bar',
            'title' => 'Monthly Sales Report'
        ]);

        if ($result['status'] === 'success') {
            $this->info('Report generated: ' . $result['data']['output_path']);

            // Email report
            Mail::to('admin@example.com')
                ->send(new MonthlyReportMail($result['data']['output_path']));
        }
    }
}
```

---

### Example 10: Real-time Image Processing API

```php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use MadeItEasyTools\Multiverse\Facades\Multiverse;

class ImageFilterController extends Controller
{
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
            'filter' => 'required|in:blur,sharpen,grayscale,sepia'
        ]);

        $path = $request->file('image')->store('temp');
        $fullPath = storage_path('app/' . $path);

        $result = Multiverse::run('image_filter', [
            'image_path' => $fullPath,
            'filter' => $validated['filter']
        ]);

        if ($result['status'] === 'success') {
            return response()->download(
                $result['data']['output_path'],
                'filtered_' . $request->file('image')->getClientOriginalName()
            )->deleteFileAfterSend();
        }

        return response()->json([
            'error' => $result['message']
        ], 500);
    }
}
```

---

## Testing Examples

### Example 11: Testing Workers

```php
namespace Tests\Feature;

use Tests\TestCase;
use MadeItEasyTools\Multiverse\Facades\Multiverse;

class ImageProcessingTest extends TestCase
{
    public function test_image_resizer_works()
    {
        $testImage = public_path('test-assets/sample.jpg');

        $result = Multiverse::run('image_resizer', [
            'image_url' => $testImage,
            'width' => 500,
            'height' => 500
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertFileExists($result['data']['output_path']);

        // Verify dimensions
        [$width, $height] = getimagesize($result['data']['output_path']);
        $this->assertEquals(500, $width);
        $this->assertEquals(500, $height);
    }

    public function test_handles_invalid_image()
    {
        $result = Multiverse::run('image_resizer', [
            'image_url' => '/nonexistent/image.jpg',
            'width' => 500,
            'height' => 500
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('not found', $result['message']);
    }
}
```

---

## Best Practices

### 1. Error Handling

Always wrap worker calls in try-catch:

```php
try {
    $result = Multiverse::run('my_worker', $data);

    if ($result['status'] === 'success') {
        // Handle success
    } else {
        // Handle worker-level error
        Log::warning('Worker returned error', $result);
    }
} catch (\Exception $e) {
    // Handle system-level error
    Log::error('Worker execution failed', [
        'error' => $e->getMessage()
    ]);
}
```

### 2. Input Validation

Validate data before passing to workers:

```php
$validated = $request->validate([
    'image' => 'required|image|max:10240',
    'width' => 'required|integer|min:1|max:4000',
]);

$result = Multiverse::run('image_resizer', $validated);
```

### 3. Queue Long-Running Tasks

```php
dispatch(new ProcessImageJob($imageUrl))->onQueue('workers');
```

### 4. Cache Results

```php
$cacheKey = 'worker:sentiment:' . md5($text);

$result = Cache::remember($cacheKey, 3600, function () use ($text) {
    return Multiverse::run('sentiment', ['text' => $text]);
});
```
