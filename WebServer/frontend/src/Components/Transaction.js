import React, { useState, useMemo } from "react";
import axios from "axios";
import { formatNumber } from "../Utils/Utilities";
import getBackendURL from "../Utils/backendURL";
import { Card, Button, Table, Form } from "react-bootstrap";

function Transaction({ stockData, chartData }) {
  const [quantity, setQuantity] = useState(1);

  const latestPrice = useMemo(() => {
    return chartData.length > 0 ? chartData[chartData.length - 1].close : null;
  }, [chartData]);

  const handleTransaction = async (type) => {
    if (!latestPrice) {
      window.alert("Price data is not available. Try again later.");
      return;
    }

    try {
      const response = await axios.post(getBackendURL(), {
        type: "PERFORM_TRANSACTION",
        transactionType: type,
        ticker: stockData.ticker,
        price: latestPrice,
        quantity: quantity,
      }, { withCredentials: true });

      if (response.status === 200 && response.data && !response.data.error) {
        window.alert(
          `Successfully ${type === "BUY" ? "bought" : "sold"} ${quantity} shares of ${stockData.ticker} at $${latestPrice.toFixed(2)}.`
        );
      } else {
        throw new Error(response.data.error || "Transaction failed.");
      }
    } catch (error) {
      window.alert(`Failed to execute ${type.toLowerCase()} transaction.\n${error}`);
    }
  };

  return (
    <Card className="bg-dark text-light shadow-sm">
      <Card.Body>
        <Card.Title className="text-center">{stockData.name} ({stockData.ticker})</Card.Title>

        <p className="text-center">
          <strong>Current Price:</strong>{" "}
          {latestPrice ? `$${latestPrice.toFixed(2)}` : "Loading..."}
        </p>

        <Form.Group className="text-center mb-3">
          <Form.Label><strong>Quantity:</strong></Form.Label>
          <Form.Control
            type="number"
            min="1"
            value={quantity}
            onChange={(e) => setQuantity(Number(e.target.value))}
            style={{ maxWidth: "100px", margin: "0 auto" }}
          />
        </Form.Group>

        <div className="d-flex justify-content-center gap-3 mb-3">
          <Button variant="success" onClick={() => handleTransaction("BUY")}>
            Buy
          </Button>
          <Button variant="danger" onClick={() => handleTransaction("SELL")}>
            Sell
          </Button>
        </div>

        <hr />

        <Table striped size="sm" className="mb-0">
          <tbody>
            <tr>
              <td><strong>Sector:</strong></td>
              <td>{stockData.sector}</td>
            </tr>
            <tr>
              <td><strong>Market Cap:</strong></td>
              <td>${formatNumber(parseFloat(stockData.marketCap))}</td>
            </tr>
            <tr>
              <td><strong>Exchange:</strong></td>
              <td>{stockData.exchange}</td>
            </tr>
            <tr>
              <td><strong>Industry:</strong></td>
              <td>{stockData.industry}</td>
            </tr>
          </tbody>
        </Table>
      </Card.Body>
    </Card>
  );
}

export default Transaction;
