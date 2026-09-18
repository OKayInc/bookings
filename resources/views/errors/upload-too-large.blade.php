<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Upload too large</title></head>
<body style="font-family:system-ui;max-width:42rem;margin:4rem auto;padding:1rem">
    <h1>Upload too large</h1>
    <p>{{ $message }}</p>
    <p>Your request was not processed. Go back and select your files again.</p>
    <button type="button" onclick="history.back()">Go back</button>
    <a href="{{ url('/') }}">Home</a>
</body>
</html>
