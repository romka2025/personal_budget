console.log("script loaded");

// =========================
// LOGIN
// =========================
function login() {
    const email    = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    if (!email || !password) {
        alert("נא למלא אימייל וסיסמה");
        return;
    }

    fetch("/project/api/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
    })
    .then(res => res.json())
    .then(data => {
        console.log("LOGIN RESPONSE:", data);

        if (data.user_id) {
            localStorage.setItem("user_id", data.user_id);
            localStorage.setItem("user_name", data.name);
            window.location.href = "dashboard.html";
        } else {
            alert("Login failed: " + (data.error || "Unknown error"));
        }
    })
    .catch(err => console.error("Login error:", err));
}

// =========================
// DASHBOARD LOADER
// =========================
function loadDashboard() {
    const userId = localStorage.getItem("user_id");

    if (!userId) {
        alert("Not logged in");
        window.location.href = "index.html";
        return;
    }

    const name = localStorage.getItem("user_name") || "משתמש";
    document.getElementById("welcomeMsg").textContent = "שלום, " + name;

    loadBalance(userId);
    loadTransactionsAndGoal(userId);  // טעינה אחת לשניהם
}

// =========================
// BALANCE
// =========================
function loadBalance(userId) {
    fetch(`/project/api/get_balance.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            console.log("BALANCE:", data);
            document.getElementById("balanceBox").innerHTML =
                `<h2>יתרה: ₪${data.balance}</h2>
                 <p>הכנסות: ₪${data.income} | הוצאות: ₪${data.expense}</p>`;
        })
        .catch(err => console.error(err));
}

// =========================
// TRANSACTIONS + GOAL (טעינה משותפת)
// =========================
function loadTransactionsAndGoal(userId) {
    fetch(`/project/api/get_transactions.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            console.log("TRANSACTIONS:", data);

            // --- טבלת עסקאות ---
            const table = document.getElementById("transactionsTable");
            table.innerHTML = `
                <tr>
                    <th>תאריך</th>
                    <th>סוג</th>
                    <th>קטגוריה</th>
                    <th>סכום</th>
                    <th>תיאור</th>
                </tr>
            `;

            data.forEach(t => {
                const row = table.insertRow();
                row.innerHTML = `
                    <td>${t.date}</td>
                    <td>${t.type === 'income' ? 'הכנסה' : 'הוצאה'}</td>
                    <td>${t.category || '—'}</td>
                    <td>₪${t.amount}</td>
                    <td>${t.description || ''}</td>
                `;
            });

            // --- חישוב יעד (Goal) ---
            let totalIncome = 0;
            data.forEach(t => {
                if (t.type === "income") totalIncome += parseFloat(t.amount);
            });

            const goal    = 10000;
            const percent = Math.min((totalIncome / goal) * 100, 100);

            document.getElementById("goalBox").innerHTML = `
                <h3>התקדמות לעבר יעד: ₪${goal}</h3>
                <div style="background:#ddd; border-radius:8px; overflow:hidden; height:20px;">
                    <div style="width:${percent}%; background:#4CAF50; height:100%;"></div>
                </div>
                <p>${percent.toFixed(1)}% (${totalIncome} ₪ מתוך ${goal} ₪)</p>
            `;
        })
        .catch(err => console.error(err));
}

// =========================
// LOGOUT
// =========================
function logout() {
    localStorage.clear();
    window.location.href = "index.html";
}
// =========================
// ADD TRANSACTION
// =========================
function addTransaction() {
    const userId     = localStorage.getItem("user_id");
    const type       = document.getElementById("txType").value;
    const amount     = document.getElementById("txAmount").value;
    const date       = document.getElementById("txDate").value;
    const desc       = document.getElementById("txDesc").value;
    const categoryId = document.getElementById("txCategory").value;

    if (!amount || !date) {
        alert("נא למלא סכום ותאריך");
        return;
    }

    fetch("/project/api/add_transaction.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            user_id:     userId,
            type:        type,
            amount:      amount,
            date:        date,
            description: desc,
            category_id: categoryId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("העסקה נוספה בהצלחה!");
            loadTransactionsAndGoal(userId); // רענון הטבלה
            loadBalance(userId);
        } else {
            alert("שגיאה: " + data.error);
        }
    });
}