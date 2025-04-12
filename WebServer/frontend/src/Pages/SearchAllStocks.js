import React, { useState } from "react";
import axios from "axios";
import { useNavigate } from "react-router-dom";
import Navbar from "../Components/Navbar";
import getBackendURL from "../Utils/backendURL";
import { formatNumber } from "../Utils/Utilities";
import { Container, Row, Col, Form, Button, Card, Alert } from "react-bootstrap";

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
      if (newRange.min > newRange.max) {
        newRange.max = newRange.min;
      }
      return newRange;
    });
  };

  return (
    <div>
      <Navbar handleLogout={handleLogout} />
      <Container fluid className="mt-4 bg-transparent px-3">
        <h1 className="text-center mb-4">Search for a Stock</h1>

        <Form onSubmit={handleSubmit} className="mb-4 ">
          <Form.Check
            inline
            type="radio"
            label="Search by Ticker"
            name="searchType"
            checked={searchBy === "ticker"}
            onChange={() => setSearchBy("ticker")}
          />
          <Form.Check
            inline
            type="radio"
            label="Search by Market Cap Range"
            name="searchType"
            checked={searchBy === "marketCap"}
            onChange={() => setSearchBy("marketCap")}
          />

          <Row className="mt-3 text-center justify-content-center">
            {searchBy === "ticker" ? (
              <Col md={6}>
                <Form.Control
                  type="text"
                  placeholder="Enter stock ticker (e.g., AAPL)"
                  value={ticker}
                  onChange={(e) => setTicker(e.target.value.toUpperCase())}
                  required
                />
              </Col>
            ) : (
              <>
                <Col xs={6} md={3}>
                  <Form.Label>Min Market Cap</Form.Label>
                  <Form.Control
                    type="number"
                    min="500000"
                    value={marketCapRange.min}
                    onChange={(e) => handleMarketCapChange(e, "min")}
                  />
                </Col>
                <Col xs={6} md={3}>
                  <Form.Label>Max Market Cap</Form.Label>
                  <Form.Control
                    type="number"
                    min="500000"
                    value={marketCapRange.max}
                    onChange={(e) => handleMarketCapChange(e, "max")}
                  />
                </Col>
                <Col xs={12}>
                  <p className="mt-2">
                    Selected Range: ${formatNumber(marketCapRange.min)} - ${formatNumber(marketCapRange.max)}
                  </p>
                </Col>
              </>
            )}
          </Row>

          <Button type="submit" className="mt-3">Search</Button>
        </Form>

        {error && (
          <Alert variant="danger">
            {error}
          </Alert>
        )}

        {stockData && stockData.length > 0 ? (
          <div>
            <h2 className="mb-3">Stock Information</h2>
            {stockData.map((stock) => (
              <Card key={stock.ticker} className="mb-3 bg-dark text-light shadow-sm">
                <Card.Body>
                  <Card.Title
                    
                    style={{ cursor: "pointer", color: "lightgreen" }}
                    onClick={() => handleTickerClick(stock.ticker)}
                  >
                    {stock.ticker} - {stock.name}
                  </Card.Title>
                  <Card.Text>
                    <strong>Market Cap:</strong> ${formatNumber(parseFloat(stock.marketCap))} <br />
                    <strong>Sector:</strong> {stock.sector} <br />
                    <strong>Industry:</strong> {stock.industry} <br />
                    <strong>Price:</strong> ${parseFloat(stock.price).toFixed(2)} <br />
                    <strong>Exchange:</strong> {stock.exchange}
                  </Card.Text>
                </Card.Body>
              </Card>
            ))}
          </div>
        ) : stockData && stockData.length === 0 ? (
          <p>No data found. Try another search.</p>
        ) : null}
      </Container>
    </div>
  );
}

export default SearchAllStocks;
