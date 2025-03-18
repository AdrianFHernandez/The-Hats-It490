import React from "react";
import { Link } from "react-router-dom";

const Navbar = ({ handleLogout }) => {
  return (
    <nav style={{
      display: "flex",
      justifyContent: "space-around",
      alignItems: "center",
      padding: "10px",
      backgroundColor: "#333",
      color: "#fff",
      marginBottom: "20px"
    }}>
      <Link to="/home" style={{ color: "#fff", textDecoration: "none", fontWeight: "bold" }}>Home</Link>
      <Link to="/searchallstocks" style={{ color: "#fff", textDecoration: "none", fontWeight: "bold" }}>Stock Search</Link>
      <Link to="/chartpage/AAPL" style={{ color: "#fff", textDecoration: "none", fontWeight: "bold" }}>Charts</Link>
      
      <button onClick={handleLogout} style={{
        backgroundColor: "red",
        color: "white",
        border: "none",
        padding: "8px 12px",
        cursor: "pointer",
        borderRadius: "5px"
      }}>Logout</button>
    </nav>
  );
};

export default Navbar;
