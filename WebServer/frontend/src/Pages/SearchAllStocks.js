import React, { useState } from "react";
import axios from "axios";
import { useNavigate } from "react-router-dom";
import Navbar from "../Components/Navbar"; // Import Navbar
import getBackendURL from "../Utils/backendURL";
import { formatNumber } from "../Utils/Utilities";

function SearchAllStocks({ user, handleLogout }) {
    const [ticker, setTicker] = useState("");
    const [error, setError] = useState("");
    const [stockData, setStockData] = useState(null);
    const [marketCapRange, setMarketCapRange] = useState({ min: 500000, max: 10000000000000 });
    const [searchBy, setSearchBy] = useState("ticker");
    const navigate = useNavigate();

    const handleTickerClick = (Ticker) => {
        navigate(`/chartpage/${Ticker}`);
    };

    const getStockInfo = async () => {
        try {
            if (searchBy === "marketCap" && marketCapRange.min <= 0) {
                setError("Minimum market cap must be greater than 0.");
                return;
            }

            const requestData = { type: "GET_STOCK_INFO" };

            if (searchBy === "ticker") {
                requestData.ticker = ticker;
            } else {
                requestData.marketCapMin = marketCapRange.min;
                requestData.marketCapMax = marketCapRange.max;
            }

            const response = await axios.post(getBackendURL(), requestData, { withCredentials: true });

            if (response.status === 200 && response.data) {
                setStockData(response.data);
            } else {
                setError("Unable to fetch stock data");
            }
        } catch (error) {
            console.error("Error connecting to server:", error);
            setError("Failed to fetch stock data.");
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setError("");
        getStockInfo();
    };

    const handleMarketCapChange = (e, bound) => {
        const value = parseFloat(e.target.value);
        setMarketCapRange((prev) => {
            const newRange = { ...prev, [bound]: value };

            // Ensure the lower bound is always <= upper bound
            if (newRange.min > newRange.max) {
                newRange.max = newRange.min;
            }
            return newRange;
        });
    };

    return (
        <div>
            {/* Navbar Added Here */}
            <Navbar handleLogout={handleLogout} />

            <h1>Search for a Stock</h1>
            <form onSubmit={handleSubmit}>
                <label>
                    <input type="radio" value="ticker" checked={searchBy === "ticker"} onChange={() => setSearchBy("ticker")} />
                    Search by Ticker
                </label>
                <label>
                    <input type="radio" value="marketCap" checked={searchBy === "marketCap"} onChange={() => setSearchBy("marketCap")} />
                    Search by Market Cap Range
                </label>
                <br />
                {searchBy === "ticker" ? (
                    <input
                        type="text"
                        placeholder="Enter stock ticker (e.g., AAPL)"
                        value={ticker}
                        onChange={(e) => setTicker(e.target.value.toUpperCase())}
                        required
                    />
                ) : (
                    <div>
                        <label>Market Cap Range:</label>
                        <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                            <span>Min:</span>
                            <input
                                type="number"
                                min="500000"
                                max="100000000000000"
                                step="1"
                                value={marketCapRange.min}
                                onChange={(e) => handleMarketCapChange(e, "min")}
                            />
                            <span>Max:</span>
                            <input
                                type="number"
                                min="500000"
                                max="100000000000000"
                                step="1"
                                value={marketCapRange.max}
                                onChange={(e) => handleMarketCapChange(e, "max")}
                            />
                        </div>
                        <p>Selected Range: ${formatNumber(marketCapRange.min)} - ${formatNumber(marketCapRange.max)}</p>
                    </div>
                )}
                <br />
                <button type="submit">Search</button>
            </form>

            {error && <p style={{ color: "red" }}>{error}</p>}

            {stockData && stockData.length > 0 ? (
                <div>
                    <h2>Stock Information:</h2>
                    {stockData.map((stock) => (
                        <div key={stock.ticker} style={{ border: "1px solid #ccc", padding: "10px", margin: "10px 0" }}>
                            <h3 onClick={() => handleTickerClick(stock.ticker)} style={{ cursor: "pointer", color: "blue" }}>
                                {stock.ticker} - {stock.name}
                            </h3>
                            <p><strong>Market Cap:</strong> ${formatNumber(parseFloat(stock.marketCap))}</p>
                            <p><strong>Sector:</strong> {stock.sector}</p>
                            <p><strong>Industry:</strong> {stock.industry}</p>
                            <p><strong>Price:</strong> ${parseFloat(stock.price).toFixed(2)}</p>
                            <p><strong>Exchange:</strong> {stock.exchange}</p>
                        </div>
                    ))}
                </div>
            ) : stockData && stockData.length === 0 ? (
                <p>No data found. Try another search.</p>
            ) : null}
        </div>
    );
}

export default SearchAllStocks;
