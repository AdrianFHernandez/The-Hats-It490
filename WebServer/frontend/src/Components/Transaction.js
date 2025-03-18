import React, { useState, useMemo } from "react";
import axios from "axios";
import { formatNumber } from "../Utils/Utilities";
import getBackendURL from "../Utils/backendURL";

function Transaction({ stockData, chartData }) {
  const [quantity, setQuantity] = useState(1);

  // console.log("stockData in Transaction:", stockData);

  // Get latest price using useMemo
  const latestPrice = useMemo(() => {
    return chartData.length > 0 ? chartData[chartData.length - 1].close : null;
  }, [chartData]);

  // Unified transaction handler for both Buy & Sell
  const handleTransaction = async (type) => {
    if (!latestPrice) {
      window.alert("Price data is not available. Try again later.");
      return;
    }

    try {
      const response = await axios.post(
        getBackendURL(),
        {
          type: "PERFORM_TRANSACTION",
          transactionType: type, // "BUY" or "SELL"
          ticker: stockData.ticker,
          price: latestPrice,
          quantity: quantity,
        },
        { withCredentials: true }
      );

      if (response.status === 200 && response.data && !response.data.error) {
        window.alert(
          `Successfully ${type === "BUY" ? "bought" : "sold"} ${quantity} shares of ${stockData.ticker} at $${latestPrice.toFixed(2)}.`
        );
      } else {
        throw new Error(response.data.error || "Transaction failed.");
      }
    } catch (error) {
      // console.error(`Error performing ${type} transaction:`, error);
      window.alert(`Failed to execute ${type.toLowerCase()} transaction.\n${error}`);
    }
  };

  return (
    <div style={{
      width: "80%",
      border: "1px solid #ccc",
      borderRadius: "8px",
      background: "#f9f9f9",
      textAlign: "center",
      fontFamily: "Arial, sans-serif",
      padding: "15px"
    }}>
      {/* Stock Name & Ticker */}
      <h3>{stockData.name} ({stockData.ticker})</h3>

      {/* Current Price */}
      <p><strong>Current Price:</strong> {latestPrice ? `$${latestPrice.toFixed(2)}` : "Loading..."}</p>

      {/* Quantity Input */}
      <label>
        <strong>Quantity:</strong>
        <input
          type="number"
          value={quantity}
          min="1"
          onChange={(e) => setQuantity(Number(e.target.value))}
          style={{
            width: "80px",
            marginLeft: "10px",
            padding: "5px",
            textAlign: "center",
            border: "1px solid #ccc",
            borderRadius: "5px"
          }}
        />
      </label>

      {/* Buy & Sell Buttons */}
      <div style={{ marginTop: "10px" }}>
        <button onClick={() => handleTransaction("BUY")} style={{
          marginRight: "10px",
          padding: "8px 12px",
          background: "green",
          color: "#fff",
          border: "none",
          borderRadius: "5px",
          cursor: "pointer"
        }}>Buy</button>

        <button onClick={() => handleTransaction("SELL")} style={{
          padding: "8px 12px",
          background: "red",
          color: "#fff",
          border: "none",
          borderRadius: "5px",
          cursor: "pointer"
        }}>Sell</button>
      </div>

      <hr style={{ margin: "15px 0" }} />

      {/* Stock Meta Table */}
      <table style={{
        width: "100%",
        borderCollapse: "collapse",
        textAlign: "left",
        fontSize: "14px"
      }}>
        <tbody>
          <tr>
            <td style={{ padding: "5px", fontWeight: "bold" }}>Sector:</td>
            <td style={{ padding: "5px" }}>{stockData.sector}</td>
          </tr>
          <tr>
            <td style={{ padding: "5px", fontWeight: "bold" }}>Market Cap:</td>
            <td style={{ padding: "5px" }}>${formatNumber(parseFloat(stockData.marketCap))}</td>
          </tr>
          <tr>
            <td style={{ padding: "5px", fontWeight: "bold" }}>Exchange:</td>
            <td style={{ padding: "5px" }}>{stockData.exchange}</td>
          </tr>
          <tr>
            <td style={{ padding: "5px", fontWeight: "bold" }}>Industry:</td>
            <td style={{ padding: "5px" }}>{stockData.industry}</td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

export default Transaction;
