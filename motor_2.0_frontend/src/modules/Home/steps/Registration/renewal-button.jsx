import React from "react";
import { Col, Button as Btn } from "react-bootstrap";
import { useHistory } from "react-router";
import { useSelector } from "react-redux";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { _haptics } from 'utils'

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const RenewalButton = ({ btnDisable, type }) => {
  const history = useHistory();
  const { temp_data, theme_conf } = useSelector((state) => state.home);

  return (
    <Col
      sm="12"
      md="12"
      lg="12"
      xl="12"
      className="d-flex justify-content-center mt-3 noOutLine"
    >
      <Btn
        style={{
          display:
            ["BAJAJ"].includes(import.meta.env.VITE_BROKER) &&
            !_.isEmpty(
              (temp_data?.agentDetails || [])?.filter(
                (item) => item?.sellerType
              )
            )
              ? "none"
              : theme_conf?.broker_config?.renewal
              ? theme_conf?.broker_config?.renewal === "No"
                ? "none"
                : "block"
              : "block",
        }}
        className={`renewBtn ${
          Theme?.leadPageBtn?.link ? Theme?.leadPageBtn?.link : ""
        }`}
        variant={"link"}
        type="button"
        disabled={btnDisable}
        onClick={() => {
            _haptics([100, 0, 50]);
          history.push(`/${type}/renewal${window.location.search}`);
        }}
      >
        Already bought from us? <u>Renew here</u> in 2 step
      </Btn>
    </Col>
  );
};

export default RenewalButton;
