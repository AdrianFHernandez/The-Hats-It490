import React, { useState } from "react";
import axios from "axios";

function TransactionsPage({ user, handleLogout }) {
    const [ticker, setTicker] = useState("");
    const [error, setError] = useState("");
    const [tickerPrices, setTickerPrices] = useState(null);

    const mockData = {          
        success: true,
        data: {
            APPLE: {
                price: 100,
                description: "Sells phones",
                sector: "Tech"
            },
            APPLY: {
                price: 200,
                description: "mock",
                sector: "bimbo"
            }
        }
    };

    const getStockInfo = async () => {
        try {
            // Uncomment and use this API request when needed
            /*
            const response = await axios.post(
                "http://www.sample.com/backend/webserver_backend.php",
                { type: "getStockInfo", ticker: ticker },
                { withCredentials: true } 
            );
            */

            const response = mockData; // Using mock data for now
            console.log("Response:", response);

            if (response.success) {
                setTickerPrices(response.data);
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

            {tickerPrices && (
                <div>
                    <h2>Stock Information:</h2>
                    {tickerPrices[ticker] ? (
                        <div>
                            <h3>{ticker}</h3>
                            <p><strong>Price:</strong> ${tickerPrices[ticker].price}</p>
                            <p><strong>Description:</strong> {tickerPrices[ticker].description}</p>
                            <p><strong>Sector:</strong> {tickerPrices[ticker].sector}</p>
                        </div>
                    ) : (
                        <p>No data found for "{ticker}". Try another ticker.</p>
                    )}
                </div>
            )}
        </div>
    );
}

export default TransactionsPage;
