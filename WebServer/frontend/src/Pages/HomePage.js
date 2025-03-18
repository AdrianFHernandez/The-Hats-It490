import React, { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import TradingChart from "../Components/TradingChart";

function HomePage({ user, handleLogout }) {
  // State variables
  const [userBalance, setUserBalance] = useState(null);
  const [haveUserBalance, gettingUserBalance] = useState(false);
  const [userInfo, setUserInfo] = useState(null);
  const [error, setError] = useState("");

  useEffect(() => {
    const getUserInfo = async () => {
      try {
        const response = await axios.post(
          "http://www.sample.com/backend/webserver_backend.php",
          { type: "GET_ACCOUNT_INFO" },
          { withCredentials: true } // Send cookies for session authentication
        );

        
        console.log("API Response is :", response);
        if (response.status === 200 && response.data) {
          const { userStocks, userBalance } = response.data.user;
          console.log("Full API Response is :", response.data);
          if (!userBalance) {
            console.error("User balance is missing from response.");
            setError("User balance data is unavailable.");
            return;
          }

          console.log("User Balance:", userBalance);

          // Store full user balance object
          gettingUserBalance(true);
          setUserBalance(userBalance);
          setUserInfo(response.data.user);
        } else {
          setError("Unable to get user balance");
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
      <h1>Welcome to your Home Page</h1>

      <Link to="/searchallstocks">
        <button>Search for stocks</button>
      </Link>

      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          <button onClick={handleLogout}>Logout</button>
          <TradingChart Ticker={"TSLA"} />

          {haveUserBalance ? (
            <h2>Your current balance is: {userBalance?.totalBalance || "N/A"}</h2>
          ) : (
            <h2>Loading your balance...</h2>
          )}

          <button onClick={handleLogout}>Logout</button>
        </div>
      ) : (
        <h3>No user data available...</h3>
      )}

      {/* Show error message if any */}
      {error && <p style={{ color: "red" }}>Error: {error}</p>}
    </div>
  );
}

export default HomePage;
