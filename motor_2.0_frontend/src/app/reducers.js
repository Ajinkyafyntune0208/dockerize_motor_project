import { combineReducers } from "redux";
import loginSlice from "modules/login/login.slice";
import homeSlice from "modules/Home/home.slice";
import quoteFilterSlice from "modules/quotesPage/filterConatiner/quoteFilter.slice";
import quoteSlice from "modules/quotesPage/quote.slice";
import proposalSlice from "modules/proposal/proposal.slice";
import paymentSlice from "modules/payment-gateway/payment-gateway.slice";
import inspectionSlice from "modules/inspection-check/inspection.slice";
import generatePdf from "modules/GeneratePdf/generate.slice";
import paymentPdfSlice from "modules/payment-pdf/paymentPdf.slice";
export default combineReducers({
  login: loginSlice,
  home: homeSlice,
  quoteFilter: quoteFilterSlice,
  quotes: quoteSlice,
  proposal: proposalSlice,
  payment: paymentSlice,
  inspection: inspectionSlice,
  generate: generatePdf,
  paymentStatus: paymentPdfSlice,
});
