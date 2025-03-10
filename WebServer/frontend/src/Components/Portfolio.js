import React from "react";

function Portfolio({ userAccount, onSelectTicker }) {
  if (!userAccount) return <p>Loading portfolio...</p>;

  const { userStocks, userBalance } = userAccount;

  return (
    <div style={styles.container}>

      <div style={styles.balanceContainer}>
        <h2>Account Balance</h2>
        <p><strong>Buying Power:</strong> ${userBalance.buyingPower.toFixed(2)}</p>
        <p><strong>Stock Balance:</strong> ${userBalance.stockBalance.toFixed(2)}</p>
        <p><strong>Total Balance:</strong> ${userBalance.totalBalance.toFixed(2)}</p>
      </div>

  
      <div style={styles.stocksContainer}>
        <h2>Owned Stocks</h2>
        {Object.keys(userStocks).length === 0 ? (
          <p>No stocks owned</p>
        ) : (
          <table style={styles.table}>
            <thead>
              <tr>
                <th style={styles.th}>Stock</th>
                <th style={styles.th}>Company Name</th>
                <th style={styles.th}>Count</th>
                <th style={styles.th}>Average Price</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(userStocks).map(([ticker, stock]) => (
                <tr key={ticker}>
                  <td
                    style={{ ...styles.td, cursor: "pointer", color: "blue" }}
                    onClick={() => onSelectTicker(ticker)}
                  >
                    {ticker}
                  </td>
                  <td style={styles.td}>{stock.companyName || "N/A"}</td>
                  <td style={styles.td}>{stock.count}</td>
                  <td style={styles.td}>${stock.averagePrice?.toFixed(2) || "N/A"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

const styles = {
  container: {
    width: "80%",
    margin: "20px auto",
    padding: "20px",
    backgroundColor: "#f9f9f9",
    borderRadius: "10px",
    boxShadow: "0 4px 8px rgba(0, 0, 0, 0.1)",
    fontFamily: "Arial, sans-serif",
  },
  balanceContainer: {
    marginBottom: "20px",
  },
  stocksContainer: {
    marginTop: "20px",
  },
  table: {
    width: "100%",
    borderCollapse: "collapse",
  },
  th: {
    borderBottom: "2px solid #ddd",
    padding: "8px",
  },
  td: {
    borderBottom: "1px solid #ddd",
    padding: "8px",
    textAlign: "center",
  },
};

export default Portfolio;
