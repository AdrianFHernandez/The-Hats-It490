import React, { useState, useMemo } from "react";


//Transaction just needs to tell what kind of transaction is happening.
function Transaction({ stockData , chartData,  onTransaction }) {
  const [quantity, setQuantity] = useState(1);

  const latestPrice = useMemo(() => {
  
    return chartData.length > 0 ? chartData[chartData.length - 1].close : null;
  }, [chartData]);

  
  const handleBuy = () => {
    if (latestPrice) {
      onTransaction("BUY", latestPrice, quantity);
    }
  };

  const handleSell = () => {
    if (latestPrice) {
      onTransaction("SELL", latestPrice, quantity);
    }
  };

  return (
  <div style={{
    width: "80%",
    border: "1px solid #ccc",
    borderRadius: "8px",
    background: "#f9f9f9",
    textAlign: "center",
    fontFamily: "Arial, sans-serif"
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
      <button onClick={handleBuy} style={{
        marginRight: "10px", 
        padding: "8px 12px", 
        background: "green", 
        color: "#fff", 
        border: "none", 
        borderRadius: "5px",
        cursor: "pointer"
      }}>Buy</button>
      
      <button onClick={handleSell} style={{
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
          <td style={{ padding: "5px" }}>${stockData.marketCap?.toLocaleString()}</td>
        </tr>
      </tbody>
    </table>


    <p style={{ marginTop: "10px", fontSize: "13px", color: "#555" }}>
      <strong>About {stockData.name}:</strong> {stockData.description}
    </p>
  </div>
);

}

export default Transaction;
