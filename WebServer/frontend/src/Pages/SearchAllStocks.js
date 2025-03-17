import React, { useState } from "react";
import axios from "axios";

function SearchAllStocks({ user, handleLogout }) {
    const [ticker, setTicker] = useState("");
    const [error, setError] = useState("");
    const [stockData, setStockData] = useState(null);
    const [marketCapRange, setMarketCapRange] = useState([0, 5000]);
    const [searchBy, setSearchBy] = useState("ticker");

 
    // stockData = [
    //     {
    //         "ticker": "AAPL",
    //         "name": "Apple Inc.",
    //         "marketCap": 3168911995000,
    //         "sector": "Technology",
    //         "industry": "Consumer Electronics",
    //         "price": 210.95,
    //         "exchange": "NASDAQ"
    //     },
    //     {
    //         "ticker": "NVDA",
    //         "name": "NVIDIA Corporation",
    //         "marketCap": 2917996000000,
    //         "sector": "Technology",
    //         "industry": "Semiconductors",
    //         "price": 119.59,
    //         "exchange": "NASDAQ"
    //     },
    //     {
    //         "ticker": "MSFT",
    //         "name": "Microsoft Corporation",
    //         "marketCap": 2874013837900,
    //         "sector": "Technology",
    //         "industry": "Software - Infrastructure",
    //         "price": 386.605,
    //         "exchange": "NASDAQ"
    //     }
    // ];

    const getStockInfo = async () => {
        try {
            if (searchBy === "marketCap" && marketCapRange[0] <= 0) {
                setError("Minimum market cap must be greater than 0.");
                return;
            }

            const requestData = { type: "GET_STOCK_INFO" };
            
            if (searchBy === "ticker") {
                requestData.ticker = ticker;
            } else {
                requestData.marketCapMin = marketCapRange[0];
                requestData.marketCapMax = marketCapRange[1];
            }

            const response = await axios.post(
                "http://www.sample.com/backend/webserver_backend.php",
                requestData,
                { withCredentials: true }
            );

            console.log("Response:", response);

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

    const handleMarketCapChange = (index, value) => {
        const newRange = [...marketCapRange];
        newRange[index] = parseFloat(value);
        if (newRange[0] > newRange[1]) {
            newRange[1] = newRange[0];
        }
        setMarketCapRange(newRange);
    };

    return (
        <div>
            <h1>Search for a Stock</h1>
            <form onSubmit={handleSubmit}>
                <label>
                    <input
                        type="radio"
                        value="ticker"
                        checked={searchBy === "ticker"}
                        onChange={() => setSearchBy("ticker")}
                    />
                    Search by Ticker
                </label>
                <label>
                    <input
                        type="radio"
                        value="marketCap"
                        checked={searchBy === "marketCap"}
                        onChange={() => setSearchBy("marketCap")}
                    />
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
                    <>
                        <label>Market Cap Range: {marketCapRange[0]}B - {marketCapRange[1]}B</label>
                        <input
                            type="number"
                            min="0.1"
                            max="5000"
                            step="0.1"
                            value={marketCapRange[0]}
                            onChange={(e) => handleMarketCapChange(0, e.target.value)}
                        />
                        <input
                            type="number"
                            min="0"
                            max="5000"
                            step="0.1"
                            value={marketCapRange[1]}
                            onChange={(e) => handleMarketCapChange(1, e.target.value)}
                        />
                    </>
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
                            <h3>{stock.ticker} - {stock.name}</h3>
                            <p><strong>Market Cap:</strong> ${(stock.marketCap / 1e9).toFixed(2)}B</p>
                            <p><strong>Sector:</strong> {stock.sector}</p>
                            <p><strong>Industry:</strong> {stock.industry}</p>
                            <p><strong>Price:</strong> ${stock.price.toFixed(2)}</p>
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
