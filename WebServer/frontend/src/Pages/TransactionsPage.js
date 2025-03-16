import React, { useState } from "react";
import axios from "axios";

function TransactionsPage({ user, handleLogout }) {
    const [ticker, setTicker] = useState("");
    const [error, setError] = useState("");
    const [tickerPrices, setTickerPrices] = useState(null);

    const getStockInfo = async () => {
        try {
            const response = await axios.post(
                "http://www.sample.com/backend/webserver_backend.php",
                { type: "GET_STOCK_INFO", ticker: ticker },
                { withCredentials: true }
            );

            console.log("Response:", response);

            // Ensure proper response structure
            if (response.status === 200 && response.data.data) {
                setTickerPrices(response.data.data);
            } else {
                setError(`Unable to get ${ticker} price`);
            }
        } catch (error) {
            console.error("Error connecting to server:", error);
            setError("Failed to fetch stock data.");
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (ticker.trim()) {
            console.log("Searching for stock:", ticker);
            setError(""); // Clear previous error
            getStockInfo();
        }
    };

    return (
        <div>
            <h1>Search for a Stock</h1>
            <form onSubmit={handleSubmit}>
                <input
                    type="text"
                    placeholder="Enter stock ticker (e.g., AAPL)"
                    value={ticker}
                    onChange={(e) => setTicker(e.target.value.toUpperCase())}
                    required
                />
                <button type="submit">Search</button>
            </form>

            {error && <p style={{ color: "red" }}>{error}</p>}

            {tickerPrices && tickerPrices[ticker] ? (
                <div>
                    <h2>Stock Information:</h2>
                    <h3>{ticker}</h3>
                    <p><strong>Company Name:</strong> {tickerPrices[ticker].companyName}</p>

                </div>
            ) : tickerPrices && !tickerPrices[ticker] ? (
                <p>No data found for "{ticker}". Try another ticker.</p>
            ) : null}
        </div>
    );
}

export default TransactionsPage;
