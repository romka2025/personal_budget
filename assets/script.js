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
    if (welcome) welcome.textContent = "שלום, " + name + " | ";

    const dateEl = document.getElementById("todayDate");
    if (dateEl) dateEl.textContent = todayHebrew() + " | ";

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
            const box = document.getElementById("balanceBox");
            if (!box) return;
            box.innerHTML = `
                <h2>יתרה: ${fmtMoney(data.balance)}</h2>
                <p>הכנסות: ${fmtMoney(data.income)} | הוצאות: ${fmtMoney(data.expense)}</p>
            `;
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
    const table = document.getElementById("transactionsTable");
    if (!table) return;

    const headExtra = withDelete ? "<th></th>" : "";
    table.innerHTML = `
        <tr>
            <th>תאריך</th>
            <th>סוג</th>
            <th>קטגוריה</th>
            <th>סכום</th>
            <th>תיאור</th>
            ${headExtra}
        </tr>
    `;

    data.forEach(t => {
        const row = table.insertRow();
        const actionCell = withDelete
            ? `<td><button class="btn-danger" onclick="deleteTransaction(${t.transaction_id})">מחק</button></td>`
            : "";
        row.innerHTML = `
            <td>${t.date}</td>
            <td>${t.type === 'income' ? 'הכנסה' : 'הוצאה'}</td>
            <td>${escapeHtml(t.category || '—')}</td>
            <td>${fmtMoney(t.amount)}</td>
            <td>${escapeHtml(t.description || '')}</td>
            ${actionCell}
        `;
    });

    const empty = document.getElementById("txEmpty");
    if (empty) empty.style.display = data.length === 0 ? "" : "none";
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

            const totalIncome = data.total_income || 0;
            const goals       = data.goals || [];

            if (goals.length === 0) {
                box.innerHTML = "<p>אין יעדים עדיין.</p>";
                return;
            }

            box.innerHTML = goals.map(g => editable
                ? renderGoalEditable(g, totalIncome)
                : renderGoalReadOnly(g, totalIncome)
            ).join("");
        })
        .catch(err => console.error("goals error:", err));
}

function renderGoalReadOnly(g, totalIncome) {
    const target  = g.target_amount;
    const percent = target > 0 ? Math.min((totalIncome / target) * 100, 100) : 0;
    const dl      = g.deadline ? `עד ${g.deadline}` : "ללא תאריך יעד";
    const desc    = g.description ? `<div class="goal-desc">${escapeHtml(g.description)}</div>` : "";
    return `
        <div class="goal-item">
            <strong>יעד: ${fmtMoney(target)}</strong> (${dl})
            ${desc}
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%;"></div>
            </div>
            <p>${percent.toFixed(1)}% (${fmtMoney(totalIncome)} מתוך ${fmtMoney(target)})</p>
        </div>
    `;
}

function renderGoalEditable(g, totalIncome) {
    const target   = g.target_amount;
    const percent  = target > 0 ? Math.min((totalIncome / target) * 100, 100) : 0;
    const descVal  = g.description || "";
    const dlVal    = g.deadline    || "";
    return `
        <div class="goal-item">
            <div class="edit-row">
                <input type="text"   id="goalDesc_${g.goal_id}"     value="${escapeAttr(descVal)}" placeholder="תיאור">
                <input type="number" id="goalAmount_${g.goal_id}"   value="${target}" step="0.01" placeholder="סכום">
                <input type="date"   id="goalDeadline_${g.goal_id}" value="${escapeAttr(dlVal)}">
                <button onclick="updateGoal(${g.goal_id})">שמור</button>
                <button class="btn-danger" onclick="deleteGoal(${g.goal_id})">מחק</button>
            </div>
            <div class="bar-bg">
                <div class="bar-fill" style="width:${percent}%;"></div>
            </div>
            <p>${percent.toFixed(1)}% (${fmtMoney(totalIncome)} מתוך ${fmtMoney(target)})</p>
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

function deleteGoal(goalId) {
    const userId = requireLogin();
    if (!userId) return;
    if (!confirm("למחוק את היעד?")) return;

    fetch(`${API}/delete_goal.php`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ user_id: userId, goal_id: goalId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) loadGoals(userId, true);
        else alert("שגיאה במחיקת יעד: " + (data.error || ""));
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
