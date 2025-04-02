import React, { useState } from 'react';
import { Button, Form, Container, Table } from 'react-bootstrap';
import axios from "axios";
import getBackendURL from "../Utils/backendURL";



function RiskProfileStockPicker() {
  const [riskLevel, setRiskLevel] = useState('');
  const [stocks, setStocks] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchStocks = async () => {
    setLoading(true);
    setError(null);
    try {
      console.log("Before sending post : ", riskLevel);
        const response = await axios.post(
            getBackendURL(), 
            {type : "GET_RECOMMENDED_STOCKS" , riskLevel}, 
            {withCredentials: true}
        );
      if (response.status === 200 && response.data) {
          setStocks(response.data.recommendedStocks);
          console.log("Stocks: ", stocks)
          console.log("Response.data.recoomended stocks: ", response.data.recommendedStocks);

      }else{
          setError("Unable to fetch stock data"); 
      }
  } catch (error) {
    console.error("Error connecting to server:", error);
    setError("Failed to fetch stock data.");
  }
  };
  return (
    <Container>
      <Form>
        <Form.Group controlId="riskProfile">
          <Form.Label>Select Your Risk Profile:</Form.Label>
          <Form.Control as="select" value={riskLevel} onChange={e => setRiskLevel(e.target.value)}>
            <option value="">Select a Risk Level</option>
            <option value="1">Conservative</option>
            <option value="2">Casual</option>
            <option value="3">Risky</option>
          </Form.Control>
        </Form.Group>
        <Button variant="primary" onClick={fetchStocks} disabled={!riskLevel}>
          Get Stock Recommendations
        </Button>
      </Form>

      {loading && <p>Loading...</p>}
      {error && <p style={{ color: 'red' }}>{error}</p>}
      {stocks.length > 0 && (
        <Table striped bordered hover size="sm">
          <thead>
            <tr>
              <th>Symbol</th>
              <th>Company Name</th>
              <th>Market Cap</th>
              <th>Beta</th>
            </tr>
          </thead>
          <tbody>
            {stocks.map((stock, index) => (
              <tr key={index}>
                <td>{stock.symbol}</td>
                <td>{stock.name}</td>
                <td>${stock.marketCap}</td>
                <td>{stock.beta}</td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Container>
  );
}

export default RiskProfileStockPicker;
