console.log("script loaded");

// =========================
// HELPERS
// =========================
const API = "/project/api";

function getUserId() {
    return localStorage.getItem("user_id");
}

function requireLogin() {
    const id = getUserId();
    if (!id) {
        alert("Not logged in");
        window.location.href = "index.html";
        return null;
    }
    return id;
}

function fmtMoney(n) {
    const num = parseFloat(n) || 0;
    return "₪" + num.toLocaleString("he-IL", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function todayHebrew() {
    return new Date().toLocaleDateString("he-IL", {
        weekday: "long",
        year:    "numeric",
        month:   "long",
        day:     "numeric"
    });
}

function escapeHtml(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, ch => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[ch]);
}
const escapeAttr = escapeHtml;

// =========================
// AUTH
// =========================
function login() {
    const email    = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    if (!email || !password) {
        alert("נא למלא אימייל וסיסמה");
        return;
    }

    fetch(`${API}/login.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ email, password })
    })
    .then(res => res.json())
    .then(data => {
        if (data.user_id) {
            localStorage.setItem("user_id",   data.user_id);
            localStorage.setItem("user_name", data.name);
            window.location.href = "dashboard.html";
        } else {
            alert("Login failed: " + (data.error || "Unknown error"));
        }
    })
    .catch(err => console.error("Login error:", err));
}

function registerUser() {
    console.log("registerUser called");

    const name     = document.getElementById("regName").value.trim();
    const email    = document.getElementById("regEmail").value.trim();
    const password = document.getElementById("regPassword").value;

    if (!name || !email || !password) {
        alert("נא למלא את כל השדות");
        return;
    }

    fetch(`${API}/register.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ name, email, password })
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); }
        catch (e) { throw new Error("Bad response (status " + res.status + "): " + text.slice(0, 200)); }

        if (data.success) {
            localStorage.setItem("user_id",   data.user_id);
            localStorage.setItem("user_name", data.name);
            window.location.href = "dashboard.html";
        } else {
            alert("Register failed: " + (data.error || "Unknown error"));
        }
    })
    .catch(err => {
        console.error("Register error:", err);
        alert("Register error: " + err.message);
    });
}

function logout() {
    localStorage.clear();
    window.location.href = "index.html";
}

// =========================
// DASHBOARD
// =========================
function loadDashboard() {
    const userId = requireLogin();
    if (!userId) return;

    const name = localStorage.getItem("user_name") || "משתמש";
    const welcome = document.getElementById("welcomeMsg");
    if (welcome) welcome.textContent = "שלום, " + name 

    const dateEl = document.getElementById("todayDate");
    if (dateEl) dateEl.textContent = todayHebrew();

    // Default the "add transaction" date to today.
    const txDateInput = document.getElementById("txDate");
    if (txDateInput && !txDateInput.value) {
        txDateInput.value = new Date().toLocaleDateString("sv-SE"); // YYYY-MM-DD, local tz
    }

    populateCategorySelect("txCategory");

    loadBalance(userId);
    loadTransactions(userId);
    loadGoals(userId,   /* editable */ false);
    loadBudgets(userId, /* editable */ false);
    initThemeButton();
}

// =========================
// MANAGE PAGE
// =========================
function initManagePage() {
    const userId = requireLogin();
    if (!userId) return;

    populateCategorySelect("budgetCategory", "expense");

    loadGoals(userId,   /* editable */ true);
    loadBudgets(userId, /* editable */ true);
    loadCategories();
    initThemeButton();
}

function onTxTypeChange() {
    populateCategorySelect("txCategory");
}

function populateCategorySelect(selectId, type) {
    const sel = document.getElementById(selectId);
    if (!sel) return;

    const userId = getUserId();
    if (!userId) return;

    const params = new URLSearchParams();
    params.set("user_id", userId);
    if (selectId === "txCategory") {
        const txType = document.getElementById("txType");
        if (txType) params.set("type", txType.value);
    } else if (type) {
        params.set("type", type);
    }

    fetch(`${API}/get_categories.php?${params.toString()}`)
        .then(res => res.json())
        .then(cats => {
            sel.innerHTML = "";
            cats.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.category_id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });
        })
        .catch(err => console.error("categories error:", err));
}


// =========================
// BALANCE
// =========================
function loadBalance(userId) {
    fetch(`${API}/get_balance.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            const expensesAmount = document.getElementById("expensesAmount");
            const incomesAmount = document.getElementById("incomesAmount");
            const balanceAmount = document.getElementById("balanceAmount");

            if (expensesAmount) expensesAmount.innerText = fmtMoney(data.expense);
            if (incomesAmount) incomesAmount.innerText = fmtMoney(data.income);
            if (balanceAmount) balanceAmount.innerText = fmtMoney(data.free_balance); 
        })
        .catch(err => console.error(err));
}


// =========================
// TRANSACTIONS (dashboard table)
// =========================
function loadTransactions(userId) {
    fetch(`${API}/get_transactions.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => renderTransactionsTable(data, /* withDelete */ false))
        .catch(err => console.error(err));
}

function renderTransactionsTable(data, withDelete) {
    const container = document.getElementById("transactionsTable");
    if (!container) return;

    // אם אנחנו בדאשבורד (withDelete = false), נציג רק 5 תנועות אחרונות לפי העיצוב בפיגמה
    const displayData = withDelete ? data : data.slice(0, 5);

    if (displayData.length === 0) {
        container.innerHTML = "<p>אין תנועות עדיין.</p>";
        return;
    }

    let html = '<div class="transactions-list">';
    
    displayData.forEach(t => {
        const isIncome = t.type === 'income';
        
        // הגדרות עיצוב לפי סוג התנועה
        const iconClass = isIncome ? 'icon-income' : 'icon-expense';
        const iconSymbol = isIncome ? '↗' : '↘'; // חצים כמו בעיצוב
        const sign = isIncome ? '+' : '-';
        const amountColor = isIncome ? 'text-green' : 'text-red';
        
        // סידור התאריך לפורמט ישראלי (DD/MM/YYYY)
        const dateParts = t.date.split('-');
        const formattedDate = dateParts.length === 3 ? `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}` : t.date;
        
        // כותרת - אם אין תיאור, נציג את שם הקטגוריה
        const title = t.description ? escapeHtml(t.description) : escapeHtml(t.category || 'תנועה');
        const categoryName = escapeHtml(t.category || 'כללי');

        // כפתור מחיקה (יוצג רק בעמוד כל התנועות, לא בדאשבורד)
        const actionCell = withDelete
            ? `<button class="btn-delete-icon" onclick="deleteTransaction(${t.transaction_id})" title="מחק תנועה">🗑️</button>`
            : "";

        // בניית השורה
        html += `
            <div class="transaction-item">
                <div class="tx-right">
                    <div class="tx-icon ${iconClass}">${iconSymbol}</div>
                    <div class="tx-details">
                        <span class="tx-title">${title}</span>
                        <span class="tx-subtitle">${categoryName} • ${formattedDate}</span>
                    </div>
                </div>
                <div class="tx-left">
                    <span class="tx-amount ${amountColor}" dir="ltr">${sign}${fmtMoney(t.amount)}</span>
                    ${actionCell}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;

    // הסתרת הודעת ה"ריק" אם קיימת ב-HTML
    const empty = document.getElementById("txEmpty");
    if (empty) empty.style.display = "none";
}


function addTransaction() {
    const userId = requireLogin();
    if (!userId) return;

    const type       = document.getElementById("txType").value;
    const amount     = document.getElementById("txAmount").value;
    const date       = document.getElementById("txDate").value;
    const desc       = document.getElementById("txDesc").value;
    const categoryId = document.getElementById("txCategory").value;

    if (!amount || !date) {
        alert("נא למלא סכום ותאריך");
        return;
    }

    fetch(`${API}/add_transaction.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
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
            document.getElementById("txAmount").value = "";
            document.getElementById("txDesc").value   = "";
            // Keep the date on today by default for quick repeat entry.
            const txDateInput = document.getElementById("txDate");
            if (txDateInput && !txDateInput.value) {
                txDateInput.value = new Date().toLocaleDateString("sv-SE");
            }
            loadTransactions(userId);
            loadBalance(userId);
            loadGoals(userId,   false);
            loadBudgets(userId, false);
        } else {
            alert("שגיאה: " + data.error);
        }
    });
}

function deleteTransaction(transactionId) {
    const userId = requireLogin();
    if (!userId) return;
    if (!confirm("למחוק את התנועה?")) return;

    fetch(`${API}/delete_transaction.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, transaction_id: transactionId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (document.getElementById("balanceBox")) {
                // Dashboard context.
                loadBalance(userId);
                loadTransactions(userId);
                loadGoals(userId,   false);
                loadBudgets(userId, false);
            } else if (document.getElementById("filterType")) {
                // Transactions page context.
                applyTransactionFilters();
            }
        } else {
            alert("שגיאה במחיקה: " + (data.error || ""));
        }
    });
}

// =========================
// GOALS
// =========================
function loadGoals(userId, editable) {
    fetch(`${API}/get_goals.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById("goalsList");
            if (!box) return;

            const freeBalance = data.free_balance || 0;
            const goals       = data.goals || [];

            if (goals.length === 0) {
                box.innerHTML = "<p>אין חסכונות עדיין.</p>";
                return;
            }

            box.innerHTML = goals.map(g => editable
                ? renderGoalEditable(g, freeBalance)
                : renderGoalReadOnly(g)
            ).join("");
        })
        .catch(err => console.error("goals error:", err));
}

function renderGoalReadOnly(g) {
    const target    = g.target_amount;
    const allocated = g.allocated_amount;
    const percent   = target > 0 ? Math.min((allocated / target) * 100, 100) : 0;
    const dl        = g.deadline ? `עד ${g.deadline}` : "ללא תאריך יעד";
    const desc      = g.description ? `<strong>${escapeHtml(g.description)}</strong>` : "<strong>חסכון</strong>";
    const realized  = g.status === "realized";

    if (realized) {
        return `
            <div class="goal-item goal-realized">
                ${desc} <span class="badge-realized">✓ מומש</span>
                <p style="color:#888;">יעד: ${fmtMoney(target)} (${dl})</p>
            </div>
        `;
    }

    return `
        <div class="goal-item">
            ${desc} — יעד: ${fmtMoney(target)} (${dl})
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%;"></div>
            </div>
            <p>הוקצה: ${fmtMoney(allocated)} מתוך ${fmtMoney(target)} (${percent.toFixed(1)}%)</p>
        </div>
    `;
}

function renderGoalEditable(g, freeBalance) {
    const target    = g.target_amount;
    const allocated = g.allocated_amount;
    const percent   = target > 0 ? Math.min((allocated / target) * 100, 100) : 0;
    const descVal   = g.description || "";
    const dlVal     = g.deadline    || "";
    const realized  = g.status === "realized";

    if (realized) {
        return `
            <div class="goal-item goal-realized">
                <div class="edit-row">
                    <strong>${escapeHtml(descVal) || "חסכון"}</strong>
                    <span class="badge-realized">✓ מומש</span>
                    <button class="btn-danger" onclick="deleteGoal(${g.goal_id})">מחק</button>
                </div>
                <p style="color:#888;">יעד: ${fmtMoney(target)} (${dlVal || "ללא תאריך"})</p>
            </div>
        `;
    }

    // Available to add = freeBalance (already excludes this goal's allocation)
    // To show max the user can set: freeBalance + current allocated
    const maxAlloc = freeBalance + allocated;

    return `
        <div class="goal-item">
            <div class="edit-row">
                <input type="text"   id="goalDesc_${g.goal_id}"     value="${escapeAttr(descVal)}" placeholder="תיאור החסכון">
                <input type="number" id="goalAmount_${g.goal_id}"   value="${target}" step="0.01" min="0.01" placeholder="סכום יעד">
                <input type="date"   id="goalDeadline_${g.goal_id}" value="${escapeAttr(dlVal)}">
                <button onclick="updateGoal(${g.goal_id})">שמור</button>
                <button class="btn-danger" onclick="deleteGoal(${g.goal_id})">מחק</button>
            </div>
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%;"></div>
            </div>
            <p>הוקצה: ${fmtMoney(allocated)} מתוך ${fmtMoney(target)} (${percent.toFixed(1)}%)</p>
            <div class="alloc-row">
                <label>הקצאה לחסכון זה:</label>
                <input type="number" id="goalAlloc_${g.goal_id}" value="${allocated}" step="0.01" min="0" max="${maxAlloc}" placeholder="סכום להקצאה">
                <button onclick="allocateGoal(${g.goal_id})">עדכן הקצאה</button>
                <button class="btn-success" onclick="realizeGoal(${g.goal_id})">✓ ממש חסכון</button>
            </div>
            <small class="alloc-hint">יתרה חופשית זמינה: ${fmtMoney(freeBalance)} (מקסימום אפשרי לחסכון זה: ${fmtMoney(maxAlloc)})</small>
        </div>
    `;
}

function addGoal() {
    const userId = requireLogin();
    if (!userId) return;

    const target      = document.getElementById("goalAmount").value;
    const deadline    = document.getElementById("goalDeadline").value;
    const description = document.getElementById("goalDesc").value.trim();

    if (!target || parseFloat(target) <= 0) {
        alert("נא להזין סכום יעד תקין");
        return;
    }

    fetch(`${API}/add_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
            user_id:       userId,
            target_amount: target,
            deadline:      deadline    || null,
            description:   description || null
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById("goalAmount").value   = "";
            document.getElementById("goalDeadline").value = "";
            document.getElementById("goalDesc").value     = "";
            loadGoals(userId, true);
        } else {
            alert("שגיאה: " + (data.error || ""));
        }
    });
}

function updateGoal(goalId) {
    const userId = requireLogin();
    if (!userId) return;

    const description = document.getElementById("goalDesc_"     + goalId).value.trim();
    const target      = document.getElementById("goalAmount_"   + goalId).value;
    const deadline    = document.getElementById("goalDeadline_" + goalId).value;

    if (!target || parseFloat(target) <= 0) {
        alert("סכום יעד חייב להיות חיובי");
        return;
    }

    fetch(`${API}/update_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
            user_id:       userId,
            goal_id:       goalId,
            target_amount: target,
            description:   description || null,
            deadline:      deadline    || null
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) loadGoals(userId, true);
        else alert("שגיאה בעדכון יעד: " + (data.error || ""));
    });
}

function allocateGoal(goalId) {
    const userId = requireLogin();
    if (!userId) return;

    const input = document.getElementById("goalAlloc_" + goalId);
    const newAmount = parseFloat(input ? input.value : "");

    if (isNaN(newAmount) || newAmount < 0) {
        alert("נא להזין סכום תקין (0 או יותר)");
        return;
    }

    fetch(`${API}/allocate_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, goal_id: goalId, new_amount: newAmount })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadBalance(userId);
            loadGoals(userId, true);
        } else {
            alert("שגיאה בהקצאה: " + (data.error || ""));
        }
    });
}

function realizeGoal(goalId) {
    const userId = requireLogin();
    if (!userId) return;
    if (!confirm("לסמן את החסכון כמומש? הכסף יחזור ליתרה החופשית.")) return;

    fetch(`${API}/realize_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, goal_id: goalId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadBalance(userId);
            loadGoals(userId, true);
        } else {
            alert("שגיאה: " + (data.error || ""));
        }
    });
}

function deleteGoal(goalId) {
    const userId = requireLogin();
    if (!userId) return;
    if (!confirm("למחוק את החסכון?")) return;

    fetch(`${API}/delete_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, goal_id: goalId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadBalance(userId);
            loadGoals(userId, true);
        }
        else alert("שגיאה במחיקת חסכון: " + (data.error || ""));
    });
}

// =========================
// BUDGETS
// =========================
function loadBudgets(userId, editable) {
    fetch(`${API}/get_budgets.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById("budgetsList");
            if (!box) return;

            if (!Array.isArray(data) || data.length === 0) {
                box.innerHTML = "<p>אין תקציבים עדיין.</p>";
                return;
            }

            box.innerHTML = data.map(b => editable
                ? renderBudgetEditable(b)
                : renderBudgetReadOnly(b)
            ).join("");
        })
        .catch(err => console.error("budgets error:", err));
}

function renderBudgetReadOnly(b) {
    const limit   = b.monthly_limit;
    const spent   = b.spent;
    const percent = limit > 0 ? Math.min((spent / limit) * 100, 100) : 0;
    const over    = spent > limit;
    return `
        <div class="budget-item">
            <strong>${escapeHtml(b.category || '—')}</strong>
            — ${fmtMoney(spent)} / ${fmtMoney(limit)}
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%; background:${over ? '#e53935' : '#4CAF50'};"></div>
            </div>
            ${over ? '<p style="color:#e53935;">חרגת מהתקציב!</p>' : ''}
        </div>
    `;
}

function renderBudgetEditable(b) {
    const limit   = b.monthly_limit;
    const spent   = b.spent;
    const percent = limit > 0 ? Math.min((spent / limit) * 100, 100) : 0;
    const over    = spent > limit;
    return `
        <div class="budget-item">
            <div class="edit-row">
                <strong>${escapeHtml(b.category || '—')}</strong>
                — הוצא ${fmtMoney(spent)} מתוך
                <input type="number" id="budgetLimit_${b.budget_id}" value="${limit}" step="0.01" style="width:110px;">
                <button onclick="saveBudgetRow(${b.budget_id}, ${b.category_id})">שמור</button>
                <button class="btn-danger" onclick="deleteBudget(${b.budget_id})">מחק</button>
            </div>
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%; background:${over ? '#e53935' : '#4CAF50'};"></div>
            </div>
            ${over ? '<p style="color:#e53935;">חרגת מהתקציב!</p>' : ''}
        </div>
    `;
}

function saveBudget() {
    const userId     = requireLogin();
    if (!userId) return;

    const categoryId = document.getElementById("budgetCategory").value;
    const limit      = document.getElementById("budgetLimit").value;

    if (!categoryId || !limit) {
        alert("נא למלא קטגוריה ומגבלה");
        return;
    }

    postBudget(userId, categoryId, limit);
}

function saveBudgetRow(budgetId, categoryId) {
    const userId = requireLogin();
    if (!userId) return;

    const input = document.getElementById("budgetLimit_" + budgetId);
    const limit = input ? input.value : "";

    if (!limit) {
        alert("מגבלה חסרה");
        return;
    }

    postBudget(userId, categoryId, limit);
}

function postBudget(userId, categoryId, limit) {
    fetch(`${API}/save_budget.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
            user_id:       userId,
            category_id:   categoryId,
            monthly_limit: limit
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const topInput = document.getElementById("budgetLimit");
            if (topInput) topInput.value = "";
            loadBudgets(userId, true);
        } else {
            alert("שגיאה: " + (data.error || ""));
        }
    });
}

function deleteBudget(budgetId) {
    const userId = requireLogin();
    if (!userId) return;
    if (!confirm("למחוק את התקציב?")) return;

    fetch(`${API}/delete_budget.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, budget_id: budgetId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) loadBudgets(userId, true);
        else alert("שגיאה במחיקת תקציב: " + (data.error || ""));
    });
}

// =========================
// CATEGORIES (manage page only)
// =========================
function loadCategories() {
    const box = document.getElementById("categoriesList");
    if (!box) return;

    const userId = getUserId();
    if (!userId) return;

    fetch(`${API}/get_categories.php?user_id=${userId}`)
        .then(res => res.json())
        .then(cats => {
            if (!Array.isArray(cats) || cats.length === 0) {
                box.innerHTML = "<p>אין קטגוריות עדיין.</p>";
                return;
            }

            box.innerHTML = `
                <table border="1" class="cat-table">
                    <tr><th>שם</th><th>סוג</th><th></th></tr>
                    ${cats.map(c => `
                        <tr>
                            <td><input id="catName_${c.category_id}" type="text" value="${escapeAttr(c.name)}"></td>
                            <td>${c.type === 'income' ? 'הכנסה' : 'הוצאה'}</td>
                            <td>
                                <button onclick="renameCategory(${c.category_id})">שמור</button>
                                <button class="btn-danger" onclick="deleteCategory(${c.category_id})">מחק</button>
                            </td>
                        </tr>
                    `).join("")}
                </table>
            `;
        })
        .catch(err => console.error("categories list error:", err));
}

function addCategory() {
    const userId = requireLogin();
    if (!userId) return;

    const name = document.getElementById("catName").value.trim();
    const type = document.getElementById("catType").value;

    if (!name) {
        alert("נא להזין שם קטגוריה");
        return;
    }

    fetch(`${API}/add_category.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, name, type })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById("catName").value = "";
            refreshCategoryDependentUI();
        } else {
            alert("שגיאה: " + (data.error || ""));
        }
    });
}

function renameCategory(categoryId) {
    const userId = requireLogin();
    if (!userId) return;

    const input   = document.getElementById("catName_" + categoryId);
    const newName = input ? input.value.trim() : "";

    if (!newName) {
        alert("שם לא יכול להיות ריק");
        return;
    }

    fetch(`${API}/update_category.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, category_id: categoryId, name: newName })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) refreshCategoryDependentUI();
        else alert("שגיאה בעדכון: " + (data.error || ""));
    });
}

function deleteCategory(categoryId) {
    const userId = requireLogin();
    if (!userId) return;

    if (!confirm("למחוק את הקטגוריה? תקציבים מקושרים יימחקו, ותנועות יישארו ללא קטגוריה.")) return;

    fetch(`${API}/delete_category.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, category_id: categoryId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) refreshCategoryDependentUI();
        else alert("שגיאה במחיקה: " + (data.error || ""));
    });
}

function refreshCategoryDependentUI() {
    const userId = getUserId();
    loadCategories();
    populateCategorySelect("budgetCategory", "expense");
    if (userId) loadBudgets(userId, true);
}

// =========================
// TRANSACTIONS PAGE
// =========================
let _allTransactions = [];

function initTransactionsPage() {
    const userId = requireLogin();
    if (!userId) return;

    fetch(`${API}/get_categories.php?user_id=${userId}`)
        .then(res => res.json())
        .then(cats => {
            const sel = document.getElementById("filterCategory");
            cats.forEach(c => {
                const opt = document.createElement("option");
                opt.value = c.category_id;
                opt.textContent = c.name + " (" + (c.type === 'income' ? 'הכנסה' : 'הוצאה') + ")";
                sel.appendChild(opt);
            });
        });

    fetch(`${API}/get_transactions.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            _allTransactions = data;
            renderTransactionsTable(data, true);
        });
    initThemeButton();    
}

function applyTransactionFilters() {
    const userId = requireLogin();
    if (!userId) return;

    fetch(`${API}/get_transactions.php?user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            _allTransactions = data;
            renderTransactionsTable(filterTransactions(data), true);
        });
}

function filterTransactions(data) {
    const type     = document.getElementById("filterType").value;
    const catId    = document.getElementById("filterCategory").value;
    const fromDate = document.getElementById("filterFrom").value;
    const toDate   = document.getElementById("filterTo").value;
    const searchEl = document.getElementById("filterSearch");
    const search   = searchEl ? searchEl.value.trim().toLowerCase() : "";

    return data.filter(t => {
        if (type && t.type !== type)                          return false;
        if (catId && String(t.category_id) !== String(catId)) return false;
        if (fromDate && t.date < fromDate)                    return false;
        if (toDate   && t.date > toDate)                      return false;
        if (search) {
            const hay = (t.description || "").toLowerCase();
            if (!hay.includes(search)) return false;
        }
        return true;
    });
}

function resetTransactionFilters() {
    document.getElementById("filterType").value     = "";
    document.getElementById("filterCategory").value = "";
    document.getElementById("filterFrom").value     = "";
    document.getElementById("filterTo").value       = "";
    const searchEl = document.getElementById("filterSearch");
    if (searchEl) searchEl.value = "";
    renderTransactionsTable(_allTransactions, true);
}



// פונקציה שמסובבת (Toggle) את המצב בלחיצה על הכפתור
function toggleDarkMode() {
    // 1. קודם כל, מחליפים את המצב הויזואלי של המסך
    document.body.classList.toggle('dark-mode');
    
    // 2. שומרים במשתנה את המצב הנוכחי (האם אנחנו עכשיו בדרק מוד או לא?)
    const isDark = document.body.classList.contains('dark-mode');
    
    // 3. עדכון כפתור מסך ההתחברות (רק אימוג'י)
    // הבדיקה if (loginBtn) מבטיחה שלא תהיה שגיאה אם אנחנו לא בדף ההתחברות
    const loginBtn = document.getElementById('login-theme-btn');
    if (loginBtn) {
        loginBtn.innerText = isDark ? '☀️' : '🌙';
    }
    
    // 4. עדכון כפתור תפריט הצד - Sidebar (אימוג'י + טקסט)
    const sidebarBtn = document.getElementById('theme-toggle');
    if (sidebarBtn) {
        sidebarBtn.innerText = isDark ? '☀️ מצב בהיר' : '🌙 מצב כהה';
    }
    
    // 5. שמירת הבחירה בזיכרון של הדפדפן כדי שהמעבר בין דפים יהיה חלק
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// פונקציית עזר לעדכון הטקסט והאייקון של הכפתור
function updateThemeButtonText(isDark) {
    const btn = document.getElementById('theme-toggle');
    if (btn) {
        btn.textContent = isDark ? '☀️ מצב בהיר' : '🌙 מצב כהה';
    }
}

// פונקציה שמריצים בכל פעם שעמוד נטען כדי שהכפתור יציג את המצב הנכון
function initThemeButton() {
    const isDark = localStorage.getItem('theme') === 'dark';
    updateThemeButtonText(isDark);
}
