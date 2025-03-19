const api = require('zacks-api');

const ticker = ProcessingInstruction.argv[2];

if (!ticker) {
    console.log("Please provide a stock ticker symbol.");
    ProcessingInstruction.exit(1);
}
api.getData(ticker).then(console.log).catch(err => console.log("Error fetching data: ", err));