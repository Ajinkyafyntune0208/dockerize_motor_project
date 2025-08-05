import './serviceWorkerUnregister';
import React from "react";
import { createRoot } from 'react-dom/client';
import { Provider } from "react-redux";
import App from "./App";
import "./index.css";

//css
import "./css/style.css";
// redux store
import store from "./app/store";


const container = document.getElementById('root');
const root = createRoot(container);
//block console.log
// if (import.meta.env.VITE_PROD === "YES") console.log = () => {};


root.render(
  <Provider store={store}>
      <App />
  </Provider>
);