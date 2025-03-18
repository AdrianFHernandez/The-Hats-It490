// utils.js

export const formatNumber = (num) => {
    if (num >= 1e12) {
      return (num / 1e12).toFixed(1) + 'T'; // Trillions
    } else if (num >= 1e9) {
      return (num / 1e9).toFixed(1) + 'B'; // Billions
    } else if (num >= 1e6) {
      return (num / 1e6).toFixed(1) + 'M'; // Millions
    } else if (num >= 1e3) {
      return (num / 1e3).toFixed(1) + 'K'; // Thousands
    } else {
      return num; //regular 
    }
  };
  