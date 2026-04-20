function login() {
  const email = document.getElementById("email").value;
  const password = document.getElementById("password").value;

  fetch("http://localhost/project/api/login.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      email: email,
      password: password
    })
  })
  .then(res => res.json())
  .then(data => {
    console.log(data);

    if (data.user_id) {
      localStorage.setItem("user_id", data.user_id);
      window.location.href = "dashboard.html";
    } else {
      alert("Login failed");
    }
  });
}

function loadDashboard() {
  const userId = localStorage.getItem("user_id");

  loadBalance(userId);
  loadTransactions(userId);
  loadGoal(userId);
}

function loadBalance(userId) {
  fetch(`api/get_balance.php?user_id=${userId}`)
    .then(res => res.json())
    .then(data => {
      document.getElementById("balanceBox").innerHTML =
        `<h2>Balance: ₪${data.balance}</h2>`;
    });
}

function loadTransactions(userId) {
  fetch(`api/get_transactions.php?user_id=${userId}`)
    .then(res => res.json())
    .then(data => {

      let table = document.getElementById("transactionsTable");

      data.slice(0, 10).forEach(t => {
        let row = table.insertRow();

        row.innerHTML = `
          <td>${t.date}</td>
          <td>${t.type}</td>
          <td>${t.category}</td>
          <td>${t.amount}</td>
        `;
      });
    });
}

function loadGoal(userId) {
  fetch(`api/get_transactions.php?user_id=${userId}`)
    .then(res => res.json())
    .then(data => {

      let income = 0;

      data.forEach(t => {
        if (t.type === "income") {
          income += parseFloat(t.amount);
        }
      });

      const goal = 10000; // זמני (אחר כך מה DB)

      let percent = (income / goal) * 100;

      document.getElementById("goalBox").innerHTML =
        `<h3>Goal Progress: ${percent.toFixed(1)}%</h3>`;
    });
}