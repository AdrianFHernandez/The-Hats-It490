import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import StockDiversityChart from "./StockDiversityChart";
import { Container, Row, Col, Table } from "react-bootstrap";

function Portfolio({ userAccount }) {
  const [selectedTicker, setSelectedTicker] = useState(null);
  const navigate = useNavigate();

  if (!userAccount) return <p className="text-center">Loading portfolio...</p>;

  const onSelectTicker = (ticker) => {
    navigate(`/chartpage/${ticker}`);
  };

  const { cashBalance, stockBalance, totalBalance } = userAccount.userBalance;

  return (
    <Container fluid className="py-4">
      <Row className="justify-content-center text-center mb-4 align-items-center">
        {/* Chart */}
        <Col xs={12} lg={6} className="mb-4 mb-lg-0">
          <StockDiversityChart userStocks={userAccount.userStocks} />
        </Col>

        {/* Account Balance */}
        <Col xs={12} lg={6} className="d-flex align-items-center justify-content-center">
          <div className="card bg-dark text-white border-primary shadow-sm w-100" style={{ maxWidth: "400px" }}>
            <div className="card-header bg-primary text-white text-center">
              <h5 className="mb-0">Account Balance</h5>
            </div>
            <div className="card-body text-center">
              <p className="mb-2"><strong>Buying Power:</strong> ${parseFloat(cashBalance).toFixed(2)}</p>
              <p className="mb-2"><strong>Stock Balance:</strong> ${parseFloat(stockBalance).toFixed(2)}</p>
              <p className="mb-0"><strong>Total Balance:</strong> ${parseFloat(totalBalance).toFixed(2)}</p>
            </div>
          </div>
        </Col>
      </Row>

      {/* Owned Stocks Table */}
      <Row className="justify-content-center">
        <Col xs={12} lg={10}>
          <h2 className="text-center mb-3 text-light">Owned Stocks</h2>
          {Object.keys(userAccount.userStocks).length === 0 ? (
            <p className="text-center text-muted">No stocks owned</p>
          ) : (
            <div className="table-responsive">
              <Table striped bordered hover variant="dark" className="text-center">
                <thead className="table-primary">
                  <tr>
                    <th>Stock</th>
                    <th>Company Name</th>
                    <th>Count</th>
                    <th>Average Price</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(userAccount.userStocks).map(([ticker, stock]) => (
                    <tr key={ticker}>
                      <td
                        style={{ cursor: "pointer", color: "lightblue" }}
                        onClick={() => onSelectTicker(ticker)}
                      >
                        {ticker}
                      </td>
                      <td>{stock.companyName || "N/A"}</td>
                      <td>{stock.count}</td>
                      <td>${parseFloat(stock.averagePrice).toFixed(2) || "N/A"}</td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </div>
          )}
        </Col>
      </Row>
    </Container>
  );
}

export default Portfolio;
