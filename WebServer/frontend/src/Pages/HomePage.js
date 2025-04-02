import React, { useState, useEffect } from "react";
import axios from "axios";
import { Link } from "react-router-dom";
import Navbar from "../Components/Navbar";  // Import Navbar
import getBackendURL from "../Utils/backendURL";
import Portfolio from "../Components/Portfolio";
import Chatbot from "../Components/Chatbot";
import RiskProfileStockPicker from "../Components/RiskProfileStockPicker";

function HomePage({ user, handleLogout }) {
  const [userBalance, setUserBalance] = useState(null);
  const [haveUserBalance, setHaveUserBalance] = useState(false);
  const [account, setAccount] = useState(null);
  const [error, setError] = useState("");

  useEffect(() => {
    const getUserInfo = async () => {
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "GET_ACCOUNT_INFO" },
          { withCredentials: true }
        );

        if (response.status === 200 && response.data) {
          setHaveUserBalance(true);
          setAccount(response.data);
          
          // Ensure user balance is updated
          if (response.data.user && response.data.user.balance !== undefined) {
            setUserBalance(response.data.user.balance);
          } else {
            setUserBalance(0); // Default to zero if not found
          }
        } else {
          setError("Unable to retrieve user balance.");
        }
      } catch (error) {
        console.error("Error fetching user info:", error);
        setError(error.message);
      }
    };

    getUserInfo();
  }, []);

  return (
    <div className="homepage container">
      <Navbar handleLogout={handleLogout} /> {/* Use Navbar here */}

      <h1>Welcome to your Home Page</h1>

      {/* <Link to="/searchallstocks">
        <button>Search for Stocks</button>
      </Link> */}

      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          {account ? <> <Portfolio userAccount={account.user} />  <Chatbot></Chatbot> <RiskProfileStockPicker></RiskProfileStockPicker></>: <p>Loading portfolio...</p>}
         
        </div>
      ) : (
        <h3>No user data available...</h3>
      )}

      {/* Show error messages if any */}
      {error && <p style={{ color: "red" }}>Error: {error}</p>}
    </div>
  );
}

export default HomePage;
