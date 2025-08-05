import { currencyFormater } from "utils";
import Style from "../../style";

export const BuyNowBtn = ({ handleClick, finalPremium, gstStatus }) => {
  return (
    <Style.BuyContainer>
      <Style.CardTopRightCenter>
        <Style.BuyButton
          onClick={handleClick}
          style={{
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            ...(import.meta.env.VITE_BROKER === "UIB" && {
              fontSize: "14px",
            }),
          }}
        >
          <div
            style={
              import.meta.env.VITE_BROKER === "UIB" ? { fontSize: "14px" } : {}
            }
          >
            {" "}
            {true ? (
              <Style.WithGstText className="withGstText">
                incl. GST
              </Style.WithGstText>
            ) : (
              <noscript />
            )}
            BUY NOW{" "}
            <span style={{ fontSize: "20px", marginLeft: "5px" }} name="buy_now">
              ₹ {currencyFormater(finalPremium)}
            </span>
          </div>
        </Style.BuyButton>
      </Style.CardTopRightCenter>
    </Style.BuyContainer>
  );
};
