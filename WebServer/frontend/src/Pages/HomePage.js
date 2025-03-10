import React from "react";

function HomePage({ user, handleLogout }) {

  
  return (
    <div className="homepage container">
      <h1>Welcome to your Home Page</h1>

      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          <button onClick={handleLogout}>Logout</button>
          <TradingChart Ticker={"TSLA"}></TradingChart>
        </div>
      ) : (
        <h3>No user data available...</h3>
      )}
    </div>
  );
}

export default HomePage;
