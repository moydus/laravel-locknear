<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rate Your Locksmith — LockNear</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, sans-serif; background: #0f2318; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #1c2706; border-radius: 24px; padding: 48px 40px; max-width: 480px; width: 100%; }
    h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
    p { color: rgba(255,255,255,0.7); margin-bottom: 28px; line-height: 1.6; }
    .stars { display: flex; gap: 12px; margin-bottom: 24px; }
    .star-label { cursor: pointer; font-size: 36px; }
    input[type="radio"] { display: none; }
    textarea { width: 100%; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 14px 16px; color: #fff; font-size: 15px; resize: vertical; min-height: 100px; margin-bottom: 20px; }
    button { width: 100%; background: #16a34a; color: #fff; border: none; border-radius: 60px; padding: 16px; font-size: 16px; font-weight: 600; cursor: pointer; }
    button:hover { background: #15803d; }
  </style>
</head>
<body>
  <div class="card">
    <h1>How did {{ $company->name }} do?</h1>
    <p>Your feedback helps us keep locksmiths accountable and improves the platform for everyone.</p>
    <form method="POST" action="/api/dispatch/review/{{ $token }}">
      @csrf
      <div class="stars">
        @for ($i = 1; $i <= 5; $i++)
          <label class="star-label" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">
            <input type="radio" name="rating" value="{{ $i }}" required>
            ⭐
          </label>
        @endfor
      </div>
      <textarea name="comment" placeholder="Optional: share more details about your experience..."></textarea>
      <button type="submit">Submit Review</button>
    </form>
  </div>
</body>
</html>
