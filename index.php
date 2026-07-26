<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Portal - Premium Professional UI</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  
  <style>
    body { background-color: #0b0f19; }
    .glass-card {
      background: rgba(22, 29, 49, 0.7);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.6);
    }
    .premium-input:focus { box-shadow: 0 0 20px rgba(37, 99, 235, 0.2); }
  </style>
</head>
<body class="text-slate-200 min-h-screen flex items-center justify-center p-4 antialiased">

  <!-- Ambient background glow -->
  <div class="fixed top-[-5%] left-[-5%] w-[450px] h-[450px] rounded-full bg-blue-600/10 blur-[100px] pointer-events-none"></div>
  <div class="fixed bottom-[-5%] right-[-5%] w-[450px] h-[450px] rounded-full bg-indigo-600/10 blur-[100px] pointer-events-none"></div>

  <div class="w-full max-w-md glass-card rounded-2xl p-8 sm:p-10 border border-slate-800 animate__animated animate__fadeInUp">
    
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xl shadow-lg shadow-blue-500/20 mb-3">
        ✉️
      </div>
      <h2 class="text-2xl font-bold tracking-tight text-white bg-gradient-to-r from-blue-400 to-slate-200 bg-clip-text text-transparent">
        Contact Support Terminal
      </h2>
    </div>

    <form action="mail.php" method="post" class="space-y-5">
      
      <!-- Input Fields -->
      <div class="group">
        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Full Name</label>
        <input type="text" name="name" placeholder="Enter your full identity" required class="premium-input w-full px-4 py-3 bg-slate-900/60 hover:bg-slate-900 focus:bg-slate-950 border border-slate-800 focus:border-blue-500/60 rounded-xl text-slate-100 text-sm outline-none transition-all duration-300">
      </div>

      <div class="group">
        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</label>
        <input type="email" name="email" placeholder="username@domain.com" required class="premium-input w-full px-4 py-3 bg-slate-900/60 hover:bg-slate-900 focus:bg-slate-950 border border-slate-800 focus:border-blue-500/60 rounded-xl text-slate-100 text-sm outline-none transition-all duration-300">
      </div>

      <div class="group">
        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Inquiry Subject</label>
        <input type="text" name="subject" placeholder="What is this regarding?" required class="premium-input w-full px-4 py-3 bg-slate-900/60 hover:bg-slate-900 focus:bg-slate-950 border border-slate-800 focus:border-blue-500/60 rounded-xl text-slate-100 text-sm outline-none transition-all duration-300">
      </div>

      <div class="group">
        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Message Content</label>
        <textarea name="message" rows="4" placeholder="Describe your structural inquiry context..." required class="premium-input w-full px-4 py-3 bg-slate-900/60 hover:bg-slate-900 focus:bg-slate-950 border border-slate-800 focus:border-blue-500/60 rounded-xl text-slate-100 text-sm outline-none resize-none transition-all duration-300"></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="grid grid-cols-2 gap-3 pt-2">
        <button 
          type="button" 
          onclick="window.location.href='http://localhost/travel/admin/booking.php'" 
          class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-sm border border-slate-700 transition-all duration-200"
        >
          Back
        </button>
        <button 
          type="reset" 
          class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-rose-900/30 text-slate-300 hover:text-rose-400 font-bold text-sm border border-slate-700 transition-all duration-200"
        >
          Clear
        </button>
      </div>

      <!-- Submit Button -->
      <button 
        type="submit" 
        name="send" 
        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm uppercase shadow-lg shadow-blue-500/10 transition-all duration-200"
      >
        Transmit Message
      </button>

      <!-- Logout -->
      <div class="text-center pt-2">
        <a 
          href="http://localhost/travel/index.php" 
          class="text-[11px] font-bold text-slate-500 hover:text-rose-400 uppercase tracking-widest transition-colors"
        >
          Logout Session
        </a>
      </div>

    </form>
  </div>

</body>
</html>