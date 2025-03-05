import React, { useEffect, useState } from "react";
import axios from "axios";


function TransactionsPage({ user, handleLogout }) {
    const [ticker, setTicker] = useState("");
    const [error, setError] = useState("");
    const [tickerPrice, setTickerPrice] = useState(null)

    const getStockInfo = async () => {
  
        try {
          const response = await axios.post(
            "http://www.sample.com/backend/webserver_backend.php",
            { type: "getStockInfo", ticker : ticker},
            { withCredentials: true } 

          );
  
          // echo json_encode([
          //   "tickerPrice" => $response["tickerPrice"]
          //]
    
          console.log(JSON.stringify(response.data));
    
          if (response.data.success) {
            setTickerPrice(response.data.tickerPrice)
          } else {
            setError("Unable to get ", {ticker}, " price");
          }
        } catch (error) {
          console.error("Error connecting to server:", error);
          setError(error);
        }

    }
    const handleSubmit = (e) => {
        e.preventDefault();
        if (ticker.trim()) {
            console.log("Searching for stock:", ticker);
        }
        
        getStockInfo()


    };

    return (
        <div>
            <h1>Search for a Stock</h1>
            <form onSubmit={handleSubmit}>
                <input
                    type="text"
                    placeholder="Enter stock ticker (e.g., AAPL)"
                    value={ticker}
                    onChange={(e) => setTimeout(setTicker(e.target.value.toUpperCase()), 5000)} // add search fill in later
                    required
                />
                <button type="submit">Search</button>
            </form>
        </div>
    );
}

export default TransactionsPage;