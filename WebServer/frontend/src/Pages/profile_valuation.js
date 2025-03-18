import React, {useState, useEffect} from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import TradingChart from "../Components/TradingChart";



function ValuationPage({user}){
    const[portfolioInfo, setPrtfolioInfo] = useState(null);
    const [error, setError] = useState("");

    useEffect(()=>{
        const getPortfolio = async () =>{
            try{
                const response = await axios.post(
                    "http://www.sample.com/backend/webserver_backend.php",
                    {type: "get_portfolio info"},
                    {withCredentials: true}
                );

                if (response.data.success){
                    setPortfolioData(response.data.portfolio);
                }else{
                    setError("Unable to fetch portfolio data");

                }
                } catch (error){
                    console.error("Error fetching portfolio data...",error);
                    setError("Error connecting to server.");
                }
            };
            getPortfolioData();
        }, []);

        return(
            <div className="valuation-page container">
              <h1>Portfolio Valuation</h1>
        
              {error && <p className="error">{error}</p>}
        
              {portfolioData ? (
                <div>
                  <h3>Total Portfolio Value: ${portfolioData.totalValue}</h3>
                 
                  <PortfolioChart data={portfolioData.chartData} />
                 
                  <h3>Your Stocks:</h3>
                  <ul>
                    {portfolioData.stocks.map(stock => (
                      <li key={stock.ticker}>
                        <strong>{stock.ticker}</strong> - {stock.quantity} shares - ${stock.currentPrice} per share
                        <br />
                        <strong>Unrealized Gain/Loss:</strong> ${stock.unrealizedGainLoss}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (
                <p>Loading portfolio...</p>
              )}
            </div>
          );
        }
        
        export default ValuationPage;