import Input from "./inputs/input/input";
import Select from "./inputs/Select/Select";
import Button from "./button/Button";
import Card from "./GlobalCard/Card";
import CardBlue from "./GlobalCard/CardBlue";
import IconlessCard from "./GlobalCard/IconlessCard";
import { CustomAccordion } from "./accordion";
import AccordionHeader from "./accordion/accordion-header";
import AccordionContent from "./accordion/accordion-content";
import { Head, Text, Typography, Marker, Error } from "./view/view";
import { Loader } from "./loader/loader";
import { LogoLoader } from "./loader/logoLoader";
import CompactCard from "./compact-card/Card";
import MultiSelect from "./multi-select/multi-select";
import Header from "./header/Header";
import { Footer, Layout } from "./footer/footer";
import Tile from "./tile/tile";
import Textbox from "./inputs/TextInput/textInput";
import Switch from "./switch/switch";
import Checkbox from "./inputs/checkbox/checkbox";
import CustomRadio from "./inputs/CustomRadio/CustomRadio";
import useUnloadBeacon from "./unload/unloadHook";
import { TabWrapper, Tab } from "./tab/Tab";
import {
  FormWrapper,
  FormGroup,
  Label,
  TextInput,
  RadioContainer,
  RadioLabel,
  RadioButton,
  Radio,
  AgeWrapper,
  DropDownWrapper,
  ErrorMsg,
  BackButton,
} from "./label-input/input";
import AbiblFooter from "./footer/Abibl/footer";
import AbiblHeader from "./header/Abibl/Header";
import FloatButton from "./float-buttons/floatButton";
import Toaster from "./toaster/toaster";
import ToasterOla from "./toaster/toaserOla";
import ToasterPolicyChange from "./toaster/toasterPolicyChange";
import Delay from "./delay/delay";
import SriyahFooter from "./footer/sriyah/footer";
import RenewbuyFooter from "./footer/renewbuy/footer";
import BajajFooter from "./footer/Bajaj/footer";
import BajajB2CFooter from "./footer/Bajaj/b2c-footer";
import TataFooter from "./footer/Tata/footer";
import AceFooter from "./footer/Ace/footer";
import CustomTooltip from "./tooltip/CustomTooltip";
import { useLoginWidget } from "./SSO/SSO";
import {
  UrlFn,
  Bajaj_rURL,
  ContentFn,
  getBrokerLogoUrl,
  ContactFn,
  brokerEmailFunction,
  getLogoCvType,
  getIRDAI,
  cinNO,
  DefaultGSTCondition,
  DefaultCPACondition,
  BrokerCategory,
  BrokerName,
  getNewNcb,
  getCalculatedNcb,
  LogoFn,
  HeaderUrlFn,
  Bajaj_HeaderURL,
} from "./Details-funtion-folder/DetailsHolder";
import NetworkStatus from "./connection-status/connection-status";
import { SimpleModal } from "./modal/Modal";

export {
  Button,
  Card,
  CardBlue,
  IconlessCard,
  Input,
  Select,
  CustomAccordion,
  AccordionHeader,
  AccordionContent,
  Head,
  Text,
  Loader,
  LogoLoader,
  Typography,
  Marker,
  Error,
  CompactCard,
  MultiSelect,
  Header,
  FormWrapper,
  FormGroup,
  Label,
  TextInput,
  RadioContainer,
  RadioLabel,
  RadioButton,
  Radio,
  AgeWrapper,
  DropDownWrapper,
  ErrorMsg,
  Footer,
  Layout,
  Tile,
  Textbox,
  BackButton,
  Switch,
  Checkbox,
  CustomRadio,
  TabWrapper,
  Tab,
  AbiblFooter,
  AbiblHeader,
  FloatButton,
  Toaster,
  ToasterOla,
  ToasterPolicyChange,
  Delay,
  SriyahFooter,
  CustomTooltip,
  useLoginWidget,
  BajajFooter,
  useUnloadBeacon,
  TataFooter,
  BajajB2CFooter,
  UrlFn,
  Bajaj_rURL,
  ContentFn,
  getBrokerLogoUrl,
  ContactFn,
  brokerEmailFunction,
  getLogoCvType,
  getIRDAI,
  cinNO,
  DefaultGSTCondition,
  DefaultCPACondition,
  BrokerCategory,
  BrokerName,
  NetworkStatus,
  getNewNcb,
  getCalculatedNcb,
  LogoFn,
  HeaderUrlFn,
  Bajaj_HeaderURL,
  SimpleModal,
  AceFooter,
  RenewbuyFooter,
};
