import swal from "sweetalert";
import _ from "lodash";
import { reloadPage } from "utils";
import { _buyNowTracking } from "analytics/quote-page/quote-tracking";
import { _discount } from "../quote-logic";

//Policy type reselection when user is redirected using pdf.
//prettier-ignore
export const _polictTypeReselect = (selectedProduct, selectedTerm, lessthan767, enquiry_id, selectedType, token, typeId, date, diffDays, NoOfDays, journey_type, _stToken, shared) => {
    if (selectedProduct) {
        // generating clicks
        // tab
        if (date && diffDays < NoOfDays()) {
          !lessthan767 &&
            selectedType === "tab2" &&
            setTimeout(() => {
              document.getElementById(`tab2`) &&
                document.getElementById(`tab2`).click();
            }, 500);
          //term selection.
          // 3 months
          !lessthan767 &&
            Number(selectedTerm) &&
            Number(selectedTerm) === 3 &&
            setTimeout(() => {
              document.getElementById(`Short Term Policy (3 months)`) &&
                document.getElementById(`Short Term Policy (3 months)`).click();
            }, 1000);
          //6 months
          !lessthan767 &&
            Number(selectedTerm) &&
            Number(selectedTerm) === 6 &&
            setTimeout(() => {
              document.getElementById(`Short Term Policy (6 months)`) &&
                document.getElementById(`Short Term Policy (6 months)`).click();
            }, 1000);
          //buy now button
          setTimeout(() => {
            document.getElementById(`buy-${selectedProduct}`) &&
              document.getElementById(`buy-${selectedProduct}`).click();
          }, 2000);
          //removing the query param "producId" to prevent the element click twice if the page is reloaded with the same url
          if (document.getElementById(`buy-${selectedProduct}`)) {
            var queryUrl = window.location.search.substring(1);
            // is there anything there ?
            if (queryUrl.length) {
              // are the new history methods available ?
              if (
                window.history != undefined &&
                window.history.replaceState != undefined
              ) {
                // if pushstate exists, ad d a new state to the history, this changes the url without reloading the page
                const newurl =
                  window.location.protocol +
                  "//" +
                  window.location.host +
                  window.location.pathname +
                  `?enquiry_id=${enquiry_id}${token ? `&xutm=${token}` : ``}${
                    journey_type ? `&journey_type=${journey_type}` : ``
                  }${typeId ? `&typeid=${typeId}` : ``}${
                    _stToken ? `&stToken=${_stToken}` : ``
                  }${shared ? `&shared=${shared}` : ``}`;
                window.history.replaceState({ path: newurl }, "", newurl);
                // query.delete("productId")
                // window.location.search = query.toString();
              }
            }
          }
        }
      }
}

//Buy Now Conditions
export const _buyNow = (
  broker_config,
  temp_data,
  quote,
  addOnsAndOthers,
  isRedirectionDone,
  token,
  handleClick,
  applicableAddons,
  type
) => {
  //Analytics | Buy Now Tracking.
  _buyNowTracking(quote, temp_data, applicableAddons, type);

  quote?.redirection_url
    ? reloadPage(quote?.redirection_url)
    : quote?.companyAlias === "hdfc_ergo" && temp_data?.carOwnership
    ? swal({
        title: "Please Note",
        text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
        icon: "info",
      })
    : [
        "hdfc_ergo",
        "future_generali",
        "liberty_videocon",
        "kotak",
        "godigit",
      ].includes(quote?.companyAlias) &&
      quote?.isRenewal === "Y" &&
      !(
        (addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
          quote?.cpaAllowed) ||
        (!addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
          !quote?.cpaAllowed)
      )
    ? swal({
        title: "Please Note",
        text: quote?.cpaAllowed
          ? "CPA was chosen in your previous policy. Therefore, selecting CPA is necessary to proceed with the purchase of this quote. Please confirm to add CPA and proceed."
          : "CPA was not present in your previous policy. Therefore, please remove CPA to purchase this quote.",
        icon: "info",
        buttons: {
          cancel: "Dismiss",
          catch: {
            text: "Confirm",
            value: "confirm",
          },
        },
        dangerMode: true,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            document.getElementById("Compulsory Personal Accident") &&
              document.getElementById("Compulsory Personal Accident").click();
            break;
          default:
            break;
        }
      })
    : (broker_config?.fiftyLakhNonPos === "yes" ||
        //This hard coded value will be removed post config setup
        import.meta.env.VITE_BROKER === "RB" ||
        (import.meta.env.VITE_BROKER === "BAJAJ" &&
          !_.isEmpty(temp_data?.agentDetails) &&
          !_.isEmpty(
            temp_data?.agentDetails?.filter((o) => o?.sellerType === "P")
          ))) &&
      quote.idv * 1 > 5000000 &&
      temp_data?.isRedirectionDone &&
      temp_data?.isRedirectionDone !== "Y" &&
      isRedirectionDone === "N" &&
      token
    ? import.meta.env.VITE_BROKER === "RB" ||
      import.meta.env.VITE_BROKER === "BAJAJ" ||
      broker_config?.fiftyLakhNonPos === "yes"
      ? swal({
          title: "Please Note",
          text: `Dear Partner, IDV greater than ₹ 50 Lakhs sum-insured is above the eligiblity limit and is a Non-POS product.It can be purchased by the customer directly through our website.Please wait while we redirect the customer to the ${
            import.meta.env.VITE_BROKER === "RB"
              ? "RenewBuy"
              : import.meta.env.VITE_BROKER === "BAJAJ"
              ? "BAJAJ Capital"
              : "the"
          } website.`,
          icon: "info",
          buttons: {
            cancel: "Cancel",
            catch: {
              text: "Confirm",
              value: "confirm",
            },
          },
          dangerMode: true,
        }).then((caseValue) => {
          switch (caseValue) {
            case "confirm":
              handleClick();
              break;
            default:
          }
        })
      : swal(
          "Please Note",
          "User IDV should not be higher than ₹ 50 Lakhs for POSP user",
          "info"
        )
    : handleClick();
};

export const _breakup = (
  quote,
  temp_data,
  isRedirectionDone,
  token,
  handleKnowMoreClick
) =>
  import.meta.env.VITE_BROKER === "BAJAJ" &&
  !_.isEmpty(temp_data?.agentDetails) &&
  !_.isEmpty(temp_data?.agentDetails?.filter((o) => o?.sellerType === "P")) &&
  quote.idv * 1 > 5000000 &&
  temp_data?.isRedirectionDone &&
  temp_data?.isRedirectionDone !== "Y" &&
  isRedirectionDone === "N" &&
  token &&
  true
    ? () => {
        swal(
          "Please Note",
          "User IDV should not be higher than ₹ 50 Lakhs for POSP user",
          "info"
        );
      }
    : () => {
        quote?.companyAlias === "hdfc_ergo" && temp_data?.carOwnership
          ? swal({
              title: "Please Note",
              text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
              icon: "info",
            })
          : quote?.noCalculation === "Y"
          ? swal(
              "Please Note",
              "Premium Breakup is not available for this quote",
              "info"
            )
          : handleKnowMoreClick("premiumBreakupPop");
      };

/*-----Addon Calculation-----*/
//This is used to return currency value
export const _addonValue = (
  quote,
  addonName,
  addonDiscountPercentage,
  isInbuilt,
  excludeGST
) => {
  let destructurePoint = `${isInbuilt ? `inBuilt` : `additional`}`;
  return parseInt(
    _discount(
      quote?.addOnsData?.[destructurePoint]?.[addonName],
      addonDiscountPercentage,
      quote?.companyAlias,
      addonName
    ) * (excludeGST ? 1 : 1.18)
  );
};

//This is used to return addon value for calculation.
export const _addonCalc = (
  quote,
  selectedAddons,
  listVar,
  list,
  addonDiscountPercentage
) => {
  let total = 0;
  (Array.isArray(selectedAddons) ? selectedAddons : []).forEach((el) => {
    if (
      !_.isEmpty(listVar) &&
      listVar?.includes(el) &&
      typeof list[el] === "number"
    ) {
      total =
        total +
        _discount(list[el], addonDiscountPercentage, quote?.companyAlias, el);
    }
  });
  return total;
};

/*--x--Addon Calculation--x--*/
